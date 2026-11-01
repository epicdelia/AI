<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Clients\ClientDeal;
use App\Domains\Clients\Models\DealMessage;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\User;
use App\Services\Prompts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A client's side of the dashboard: their own requests (deals), proposals, deliverables,
 * payments and messages with her. Every query starts from the signed-in client's own deals, so
 * someone else's deal is simply "not found". What they see of a deal is ClientDeal's allow-list.
 */
class PortalController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $deals = $this->mine($request)->latest()->get();
        $open = $deals->filter(fn (Deal $d) => ! in_array($d->stage, ['paid', 'lost'], true));
        $next = $deals->filter(fn (Deal $d) => $d->meeting_at && $d->meeting_at->isFuture())->sortBy('meeting_at')->first();

        return response()->json([
            'creator' => Prompts::name(),
            'counts' => [
                'open' => $open->count(),
                'done' => $deals->where('stage', 'paid')->count(),
                'unread' => DealMessage::whereIn('deal_id', $deals->pluck('id'))->where('from_admin', true)->whereNull('read_at')->count(),
            ],
            'next_call' => $next ? ['deal_id' => $next->id, 'title' => ClientDeal::summary($next)['title'], 'at' => $next->meeting_at->toIso8601String()] : null,
            'needs_you' => $deals->map(fn (Deal $d) => ($why = ClientDeal::needsYou($d)) ? ['deal_id' => $d->id, 'title' => ClientDeal::summary($d)['title'], 'what' => $why] : null)
                ->filter()->values(),
            'recent' => $open->take(4)->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'requests' => $this->mine($request)->latest()->get()->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
            'services' => self::services(),
        ]);
    }

    public function show(Request $request, int $deal): JsonResponse
    {
        return response()->json(['request' => ClientDeal::detail($this->find($request, $deal))]);
    }

    /** "New request": a new inquiry in her pipeline, already linked to this client. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', Rule::in(array_keys(BookingController::PROJECT_LABELS))],
            'details' => 'required|string|min:10|max:4000',
            'budget' => 'nullable|string|max:100',
            'deadline' => 'nullable|string|max:100',
        ]);
        $user = $request->user();
        $label = BookingController::PROJECT_LABELS[$data['service']];

        $deal = new Deal([
            'brand_name' => $user->company ?: $user->name,
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'stage' => 'inquiry',
            'rate' => BookingController::PROJECT_RATES[$data['service']] ?? 0,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'deliverables' => [['platform' => 'request', 'format' => $label, 'project_type' => $data['service']]],
            'notes' => "Requested from the client dashboard.\n\n{$data['details']}"
                .(! empty($data['budget']) ? "\n\nBudget: {$data['budget']}" : '').(! empty($data['deadline']) ? "\nDeadline: {$data['deadline']}" : ''),
            'source' => 'portal',
        ]);
        $deal->user_id = $user->id;
        $deal->save();
        // Their own words open the thread, so she can answer them right there.
        $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => $data['details']]);

        AgentEvent::log('lead', "New request from {$user->name}".($user->company ? " ({$user->company})" : '').": {$label}.");
        self::tellAdmins("New request from {$user->name}: {$label}", "{$user->name} ({$user->email}) asked for: {$label}\n\n{$data['details']}\n\nOpen it: ".url('/deals'));

        return response()->json(['message' => 'Request sent! You’ll hear back soon.', 'request' => ClientDeal::summary($deal->fresh())], 201);
    }

    public function accept(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        abort_unless($deal->proposal && $deal->stage !== 'lost', 409, 'There’s no proposal to accept yet.');
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'agree' => 'accepted']);

        return DealRoomController::acceptDeal($deal, $data['name']);
    }

    public function pay(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        if (! ClientDeal::canPay($deal)) {
            return response()->json(['message' => $deal->payment_status === 'paid' ? 'This is already paid. Thank you!' : 'There’s nothing to pay online for this request yet.'], 409);
        }

        return DealRoomController::checkout($deal, '/requests/'.$deal->id);
    }

    public function messages(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $deal->messages()->where('from_admin', true)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['messages' => $deal->messages()->with('user:id,name')->oldest()->get()->map->toThread()->values()]);
    }

    public function sendMessage(Request $request, int $deal): JsonResponse
    {
        $deal = $this->find($request, $deal);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $user = $request->user();
        $message = $deal->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => trim($data['body'])]);

        AgentEvent::log('lead', "{$user->name} sent you a message about ".ClientDeal::summary($deal)['title'].'.');
        self::tellAdmins("New message from {$user->name}", "{$user->name} wrote:\n\n".trim($data['body'])."\n\nReply in the studio: ".url('/clients'));

        return response()->json(['message' => $message->load('user:id,name')->toThread()], 201);
    }

    public function billing(Request $request): JsonResponse
    {
        $deals = $this->mine($request)->get();
        $byId = $deals->keyBy('id');

        return response()->json([
            'payments' => Payment::whereIn('deal_id', $deals->pluck('id'))->latest('paid_at')->get()->map(fn (Payment $p) => [
                'id' => $p->id,
                'request_id' => $p->deal_id,
                'title' => ClientDeal::summary($byId[$p->deal_id])['title'],
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
                'kind' => $p->kind,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values(),
            'due' => $deals->filter(fn (Deal $d) => ClientDeal::canPay($d))->map(fn (Deal $d) => ClientDeal::summary($d))->values(),
            'retainers' => $deals->filter(fn (Deal $d) => $d->subscription_status)->map(fn (Deal $d) => ClientDeal::summary($d) + ['retainer' => $d->subscription_status])->values(),
        ]);
    }

    /** @return list<array{key: string, label: string}> */
    public static function services(): array
    {
        return array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(BookingController::PROJECT_LABELS), BookingController::PROJECT_LABELS);
    }

    /** Email every admin (through her SMTP); never blocks the client's action. */
    public static function tellAdmins(string $subject, string $body): void
    {
        foreach (User::where('role', Role::Admin->value)->whereNull('disabled_at')->pluck('email') as $email) {
            AuthController::mail($email, $subject, $body);
        }
    }

    private function mine(Request $request): Builder
    {
        return Deal::where('user_id', $request->user()->id)->whereIn('stage', ClientDeal::visibleStages());
    }

    private function find(Request $request, int $id): Deal
    {
        return $this->mine($request)->whereKey($id)->firstOrFail();
    }
}
