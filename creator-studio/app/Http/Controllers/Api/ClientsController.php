<?php

namespace App\Http\Controllers\Api;

use App\Domains\Clients\ClientDeal;
use App\Domains\Clients\ClientLink;
use App\Domains\Clients\Models\DealMessage;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\User;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Her Clients page (Role ability `clients.manage`): every client account, the deals linked to
 * each, switching an account off, confirming an email by hand (when mail is down), invites to
 * the dashboard, and the message thread on each deal.
 */
class ClientsController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = User::where('role', Role::Client->value)->withCount('deals')->latest()->get();
        $unread = DealMessage::where('from_admin', false)->whereNull('read_at')
            ->join('deals', 'deals.id', '=', 'deal_messages.deal_id')
            ->selectRaw('deals.user_id, count(*) as n')->groupBy('deals.user_id')->pluck('n', 'deals.user_id');
        $due = Deal::whereIn('user_id', $clients->pluck('id'))->where('payment_status', '!=', 'paid')
            ->whereIn('stage', ['invoiced'])->selectRaw('user_id, sum(rate) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return response()->json([
            'clients' => $clients->map(fn (User $u) => $this->row($u) + [
                'requests' => $u->deals_count,
                'unread' => (int) ($unread[$u->id] ?? 0),
                'due' => (float) ($due[$u->id] ?? 0),
            ])->values(),
            'unread_total' => (int) $unread->sum(),
        ]);
    }

    public function show(int $client): JsonResponse
    {
        $user = $this->client($client);

        return response()->json([
            'client' => $this->row($user) + ['phone' => $user->phone],
            'deals' => $user->deals()->latest()->get()->map(fn (Deal $d) => [
                'id' => $d->id, 'brand_name' => $d->brand_name, 'stage' => $d->stage, 'rate' => (float) $d->rate,
                'currency' => $d->currency, 'payment_status' => $d->payment_status,
                'client_status' => ClientDeal::status($d)['label'],
                'unread' => $d->messages()->where('from_admin', false)->whereNull('read_at')->count(),
            ])->values(),
            // Deals from the same email not linked to anyone yet: one click links them.
            'suggested' => Deal::whereNull('user_id')->where('stage', '!=', 'prospect')
                ->whereRaw('lower(contact_email) = ?', [strtolower($user->email)])->get(['id', 'brand_name', 'stage']),
        ]);
    }

    public function update(Request $request, int $client): JsonResponse
    {
        $user = $this->client($client);
        $data = $request->validate([
            'disabled' => 'sometimes|boolean',
            'verified' => 'sometimes|accepted',
            'name' => 'sometimes|string|max:100',
            'company' => 'sometimes|nullable|string|max:150',
        ]);

        if (array_key_exists('disabled', $data)) {
            $user->forceFill(['disabled_at' => $data['disabled'] ? now() : null])->save();
            if ($data['disabled']) {
                AuthController::endSessions($user);
            }
        }
        if (! empty($data['verified']) && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            ClientLink::claim($user);
        }
        $user->update(array_intersect_key($data, array_flip(['name', 'company'])));

        return response()->json(['message' => 'Saved.', 'client' => $this->row($user->fresh())]);
    }

    public function resendVerification(int $client): JsonResponse
    {
        $user = $this->client($client);
        abort_if($user->hasVerifiedEmail(), 409, 'Their email is already confirmed.');
        rescue(fn () => $user->sendEmailVerificationNotification(), null, true);

        return response()->json(['message' => "Confirmation link sent to {$user->email}."]);
    }

    public function linkDeal(Request $request, int $client): JsonResponse
    {
        $user = $this->client($client);
        $deal = Deal::findOrFail($request->validate(['deal_id' => 'required|integer'])['deal_id']);
        $deal->forceFill(['user_id' => $user->id])->save();

        return response()->json(['message' => "Linked to {$user->name}."]);
    }

    public function unlinkDeal(int $client, Deal $deal): JsonResponse
    {
        $user = $this->client($client);
        abort_unless($deal->user_id === $user->id, 404);
        $deal->forceFill(['user_id' => null])->save();

        return response()->json(['message' => 'Unlinked.']);
    }

    /** Email the deal's contact a link to their dashboard (sign up, or sign in if they have one). */
    public function invite(Deal $deal): JsonResponse
    {
        abort_unless($deal->contact_email, 422, 'Add the contact’s email to this deal first.');
        $email = strtolower(trim($deal->contact_email));
        $existing = User::whereRaw('lower(email) = ?', [$email])->first();
        $from = Prompts::name();

        if ($existing) {
            if ($existing->role() === Role::Client && $existing->hasVerifiedEmail() && ! $deal->user_id) {
                $deal->forceFill(['user_id' => $existing->id])->save();
            }
            $body = "Hi {$deal->contact_name},\n\n{$from} added \"{$deal->brand_name}\" to your client dashboard. Sign in to follow it, see proposals and invoices, and message {$from}:\n\n".url('/login');
        } else {
            $body = "Hi {$deal->contact_name},\n\n{$from} invited you to your own client dashboard: follow \"{$deal->brand_name}\", accept proposals, pay invoices and message {$from} in one place.\n\nCreate your account with this email ({$email}):\n\n".url('/login?'.http_build_query(['signup' => 1, 'email' => $email]));
        }
        $sent = AuthController::mail($email, "Your client dashboard with {$from}", $body);

        return response()->json(['message' => $sent ? "Invite sent to {$email}." : 'The email couldn’t be sent: check the SMTP settings in .env.'], $sent ? 200 : 502);
    }

    public function messages(Deal $deal): JsonResponse
    {
        $deal->messages()->where('from_admin', false)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'client' => $deal->client ? $this->row($deal->client) : null,
            'messages' => $deal->messages()->with('user:id,name')->oldest()->get()->map->toThread()->values(),
        ]);
    }

    public function sendMessage(Request $request, Deal $deal): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:5000']);
        abort_unless($deal->user_id, 422, 'Link this deal to a client account first (or invite them).');
        $message = $deal->messages()->create(['user_id' => $request->user()->id, 'from_admin' => true, 'body' => trim($data['body'])]);

        if ($deal->client && ! $deal->client->isDisabled()) {
            AuthController::mail($deal->client->email, 'New message from '.Prompts::name(),
                Prompts::name()." wrote about \"".ClientDeal::summary($deal)['title']."\":\n\n".trim($data['body'])."\n\nReply in your dashboard: ".url('/requests/'.$deal->id));
        }

        return response()->json(['message' => $message->load('user:id,name')->toThread()], 201);
    }

    private function client(int $id): User
    {
        return User::where('role', Role::Client->value)->findOrFail($id);
    }

    private function row(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'company' => $u->company,
            'verified' => $u->hasVerifiedEmail(),
            'disabled' => $u->isDisabled(),
            'two_factor' => (bool) $u->hasTwoFactor(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
