<?php

namespace App\Http\Controllers\Api;

use App\Domains\Crm\Models\CrmActivity;
use App\Domains\Crm\Models\CrmContact;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Her contacts in one place: leads, clients, sponsor contacts and engaged followers, each
 * with a stage, value, next follow-up and a timeline of notes, calls, emails, meetings and
 * tasks. "Sync" pulls in people she already knows (deal contacts and client accounts),
 * without duplicates.
 */
class CrmController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'stage' => 'nullable|in:' . implode(',', CrmContact::STAGES),
            'due' => 'nullable|boolean',
        ]);
        $q = CrmContact::query()
            ->when($data['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")->orWhere('company', 'like', "%{$s}%")))
            ->when($data['stage'] ?? null, fn ($q, $st) => $q->where('stage', $st))
            ->when($data['due'] ?? false, fn ($q) => $q->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay()))
            ->orderByRaw('next_follow_up_at is null')->orderBy('next_follow_up_at')->latest('updated_at');

        $byStage = CrmContact::select('stage', DB::raw('count(*) as n'), DB::raw('sum(value) as v'))->groupBy('stage')->get()->keyBy('stage');

        return response()->json([
            'contacts' => $q->take(500)->get()->map(fn (CrmContact $c) => $this->row($c))->values(),
            'stages' => collect(CrmContact::STAGES)->map(fn ($s) => [
                'stage' => $s,
                'count' => (int)($byStage[$s]->n ?? 0),
                'value' => (float)($byStage[$s]->v ?? 0),
            ]),
            'due' => CrmContact::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $c = CrmContact::findOrFail($id);
        $deals = $c->email ? Deal::whereRaw('lower(contact_email) = ?', [strtolower($c->email)])
            ->when($c->user_id, fn ($q) => $q->orWhere('user_id', $c->user_id))
            ->latest()->take(20)->get(['id', 'brand_name', 'stage', 'rate', 'currency', 'payment_status', 'created_at']) : collect();

        return response()->json([
            'contact' => $this->row($c),
            'activities' => $c->activities()->take(200)->get()->map(fn (CrmActivity $a) => $this->activityRow($a))->values(),
            'deals' => $deals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        if (!empty($data['email']) && CrmContact::whereRaw('lower(email) = ?', [strtolower($data['email'])])->exists()) {
            abort(422, 'A contact with that email exists already.');
        }
        $c = CrmContact::create($data + ['source' => $data['source'] ?? 'manual']);

        return response()->json(['contact' => $this->row($c)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $c = CrmContact::findOrFail($id);
        $data = $this->validated($request, false);
        $stageBefore = $c->stage;
        $c->update($data);
        if ($c->stage !== $stageBefore) {
            $c->activities()->create(['kind' => 'note', 'body' => "Stage: {$stageBefore} → {$c->stage}", 'user_id' => $request->user()->id]);
        }

        return response()->json(['contact' => $this->row($c->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        CrmContact::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function addActivity(Request $request, int $id): JsonResponse
    {
        $c = CrmContact::findOrFail($id);
        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', CrmActivity::KINDS),
            'body' => 'required|string|max:5000',
            'due_at' => 'nullable|date',
            'next_follow_up_at' => 'nullable|date',
        ]);
        $a = $c->activities()->create([
            'kind' => $data['kind'],
            'body' => $data['body'],
            'due_at' => $data['kind'] === 'task' ? ($data['due_at'] ?? null) : null,
            'user_id' => $request->user()->id,
        ]);
        $touch = in_array($data['kind'], ['call', 'email', 'meeting'], true);
        $c->forceFill(array_filter([
            'last_contacted_at' => $touch ? now() : null,
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            'stage' => $touch && $c->stage === 'lead' ? 'contacted' : null,
        ]))->save();

        return response()->json([
            'activity' => $this->activityRow($a),
            'contact' => $this->row($c->fresh()),
        ], 201);
    }

    public function completeActivity(int $id, int $activity): JsonResponse
    {
        $a = CrmActivity::where('contact_id', $id)->findOrFail($activity);
        $a->update(['done_at' => $a->done_at ? null : now()]);

        return response()->json(['activity' => $this->activityRow($a)]);
    }

    /**
     * Pull in deal contacts and client accounts she doesn't have yet (matched by email).
     */
    public function sync(): JsonResponse
    {
        $known = CrmContact::whereNotNull('email')->pluck('email')->map(fn ($e) => strtolower($e))->flip();
        $added = 0;
        DB::transaction(function () use (&$known, &$added) {
            foreach (User::where('role', Role::Client->value)->get(['id', 'name', 'email', 'company', 'phone']) as $u) {
                $e = strtolower($u->email);
                if (isset($known[$e])) {
                    continue;
                }
                CrmContact::create(['name' => $u->name, 'email' => $u->email, 'company' => $u->company, 'phone' => $u->phone, 'source' => 'client', 'stage' => 'customer', 'user_id' => $u->id]);
                $known[$e] = true;
                $added++;
            }
            foreach (Deal::whereNotNull('contact_email')->where('stage', '!=', 'prospect')->latest()->get(['brand_name', 'contact_name', 'contact_email', 'stage', 'rate']) as $d) {
                $e = strtolower(trim((string)$d->contact_email));
                if ($e === '' || isset($known[$e]) || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                CrmContact::create([
                    'name' => $d->contact_name ?: $d->brand_name,
                    'email' => $d->contact_email,
                    'company' => $d->brand_name,
                    'source' => 'deal',
                    'stage' => $d->stage === 'paid' ? 'customer' : ($d->stage === 'lost' ? 'lost' : ($d->stage === 'inquiry' ? 'lead' : 'qualified')),
                    'value' => (float)$d->rate,
                ]);
                $known[$e] = true;
                $added++;
            }
        });

        return response()->json([
            'added' => $added,
            'message' => $added ? "Added {$added} contact(s)." : 'Everyone is in your CRM already.',
        ]);
    }

    /**
     * All contacts as CSV.
     */
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Company', 'Phone', 'Stage', 'Source', 'Value', 'Tags', 'Last contacted', 'Next follow-up', 'Notes']);
            CrmContact::orderBy('name')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    $row = [$c->name, $c->email, $c->company, $c->phone, $c->stage, $c->source, (string)$c->value, implode(', ', $c->tags ?? []),
                        $c->last_contacted_at?->toDateString(), $c->next_follow_up_at?->toDateString(), $c->notes];
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $row));
                }
            });
            fclose($out);
        }, 'contacts.csv', ['Content-Type' => 'text/csv']);
    }

    // ==========================================================================================

    private function row(CrmContact $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'company' => $c->company,
            'phone' => $c->phone,
            'source' => $c->source,
            'stage' => $c->stage,
            'tags' => $c->tags ?? [],
            'value' => (float)$c->value,
            'notes' => $c->notes,
            'user_id' => $c->user_id,
            'last_contacted_at' => $c->last_contacted_at?->toIso8601String(),
            'next_follow_up_at' => $c->next_follow_up_at?->toIso8601String(),
            'follow_up_due' => $c->next_follow_up_at !== null && $c->next_follow_up_at->lte(now()->endOfDay()),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    private function activityRow(CrmActivity $a): array
    {
        return [
            'id' => $a->id,
            'kind' => $a->kind,
            'body' => $a->body,
            'due_at' => $a->due_at?->toIso8601String(),
            'done_at' => $a->done_at?->toIso8601String(),
            'at' => $a->created_at?->toIso8601String(),
        ];
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'email' => 'nullable|email|max:200',
            'company' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:40',
            'stage' => 'sometimes|in:' . implode(',', CrmContact::STAGES),
            'tags' => 'nullable',
            'value' => 'nullable|numeric|min:0|max:100000000',
            'notes' => 'nullable|string|max:10000',
            'next_follow_up_at' => 'nullable|date',
        ]);
        if (array_key_exists('tags', $data)) {
            $tags = is_array($data['tags']) ? $data['tags'] : explode(',', (string)$data['tags']);
            $data['tags'] = array_values(array_slice(array_filter(array_map(fn ($t) => mb_substr(trim((string)$t), 0, 40), $tags)), 0, 12));
        }
        if (array_key_exists('value', $data)) {
            $data['value'] = (float)($data['value'] ?? 0);
        }

        return $data;
    }
}
