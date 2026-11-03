<?php

namespace App\Http\Controllers\Api;

use App\Domains\Engagement\Engagement;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Engagement\Models\EngagementRule;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The Inbox: comments and DMs from connected accounts, replies, and her automation rules.
 */
class EngagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');
        $q = EngagementItem::with(['post:id,title,body,platform,url', 'rule:id,name,dm_text'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['new', 'drafted', 'failed'])->where('intent', '!=', 'spam'))
            ->when($status === 'replied', fn ($q) => $q->where('status', 'replied'))
            ->when($status === 'ignored', fn ($q) => $q->where(fn ($q) => $q->where('status', 'ignored')->orWhere('intent', 'spam')))
            ->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('intent'), fn ($q, $i) => $q->where('intent', $i));

        $connected = SocialAccount::where('status', 'connected')->pluck('platform')->all();

        return response()->json([
            'items' => $q->latest('received_at')->limit(100)->get(),
            'counts' => [
                'open' => EngagementItem::whereIn('status', ['new', 'drafted', 'failed'])->where('intent', '!=', 'spam')->count(),
                'replied' => EngagementItem::where('status', 'replied')->count(),
                'ignored' => EngagementItem::where(fn ($q) => $q->where('status', 'ignored')->orWhere('intent', 'spam'))->count(),
            ],
            'networks' => collect(Connections::PROVIDERS)->keys()->map(fn ($p) => [
                'platform' => $p,
                'supports' => Connections::provider($p)->engagement(),
                'connected' => in_array($p, $connected, true),
            ])->values(),
            'last_poll' => collect($connected)->mapWithKeys(fn ($p) => [
                $p => \App\Models\Setting::get("engage_since_{$p}"),
            ]),
            'rules' => EngagementRule::orderBy('id')->get(),
            'triggers' => EngagementRule::TRIGGERS,
        ]);
    }

    public function poll(Engagement $engagement): JsonResponse
    {
        @set_time_limit(180);

        return response()->json($engagement->poll());
    }

    public function draft(EngagementItem $item, Engagement $engagement): JsonResponse
    {
        try {
            $item->update([
                'reply_draft' => $engagement->draft($item, $item->rule?->instructions),
                'status' => $item->status === 'new' ? 'drafted' : $item->status,
            ]);

            return response()->json([
                'item' => $item->fresh(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Draft replies for everything open that has none yet (up to 15 at a time).
     */
    public function draftAll(Engagement $engagement): JsonResponse
    {
        @set_time_limit(300);
        $done = 0;
        foreach (EngagementItem::where('status', 'new')->where('intent', '!=', 'spam')->whereNull('reply_draft')->latest('received_at')->limit(15)->get() as $item) {
            try {
                $item->update([
                    'reply_draft' => $engagement->draft($item, $item->rule?->instructions),
                    'status' => 'drafted',
                ]);
                $done++;
            } catch (Throwable $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'drafted' => $done,
                ], 422);
            }
        }

        return response()->json([
            'drafted' => $done,
        ]);
    }

    public function reply(Request $request, EngagementItem $item, Engagement $engagement): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:2200'],
            'dm' => ['nullable', 'string', 'max:1000'],
        ]);
        if (trim((string)($data['text'] ?? '')) === '' && trim((string)($data['dm'] ?? '')) === '') {
            return response()->json([
                'error' => 'Write a reply first.',
            ], 422);
        }

        try {
            // A reply she wrote or changed teaches the AI her style; an untouched AI draft doesn't.
            $by = $item->reply_draft && trim((string)$data['text']) === trim($item->reply_draft) ? 'ai' : 'her';
            $engagement->send($item, $data['text'] ?? null, $data['dm'] ?? null, $by);

            return response()->json([
                'item' => $item->fresh(),
            ]);
        } catch (Throwable $e) {
            $item->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function ignore(EngagementItem $item): JsonResponse
    {
        $item->update([
            'status' => $item->status === 'ignored' ? 'new' : 'ignored',
        ]);

        return response()->json([
            'item' => $item->fresh(),
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        return response()->json([
            'rule' => EngagementRule::create($this->rule($request)),
        ], 201);
    }

    public function updateRule(Request $request, EngagementRule $rule): JsonResponse
    {
        $rule->update($this->rule($request, true));

        return response()->json([
            'rule' => $rule->fresh(),
        ]);
    }

    public function destroyRule(EngagementRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    private function rule(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$req, 'string', 'max:80'],
            'platforms' => [$req, 'array', 'min:1'],
            'platforms.*' => [Rule::in(['instagram', 'threads', 'youtube'])],
            'trigger' => [$req, Rule::in(array_keys(EngagementRule::TRIGGERS))],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:40'],
            'reply_mode' => ['sometimes', Rule::in(['ai', 'template', 'none'])],
            'reply_template' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:1500'],
            'dm_text' => ['nullable', 'string', 'max:1000'],
            'auto_send' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'max_per_hour' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);
        if (($data['trigger'] ?? null) === 'keyword' && empty(array_filter($data['keywords'] ?? [], fn ($k) => trim($k) !== ''))) {
            abort(response()->json([
                'error' => 'Add at least one keyword.',
                'message' => 'Add at least one keyword.',
            ], 422));
        }
        if (($data['reply_mode'] ?? null) === 'template' && trim((string)($data['reply_template'] ?? '')) === '') {
            abort(response()->json([
                'error' => 'Write the reply text, or let the AI write it.',
                'message' => 'Write the reply text, or let the AI write it.',
            ], 422));
        }

        return $data;
    }
}
