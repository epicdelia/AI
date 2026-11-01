<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Deals\FollowUps;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Landing\Attribution;
use App\Domains\Workflows\Engine;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Services\Llm;
use App\Services\Prompts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Social proof and an owned audience: testimonials (a private link per client, shown on the
 * landing page once she approves them), the landing page's email opt-in with a free download,
 * and inbox leads turned into deals. Nothing is emailed or posted for her.
 */
class SocialProofController extends Controller
{
    public const MAGNET = 'lead_magnet';

    public const MAGNET_DEFAULTS = [
        'enabled' => false,
        'title' => 'Get my AI engineering toolkit',
        'description' => 'The tools, prompts and checklists I use to ship AI features fast. Free.',
        'button' => 'Send me the toolkit',
        'delivery_url' => '',
        'delivery_label' => 'Open the toolkit',
    ];

    // ------------------------------------------------------------ public

    public function publicTestimonials(): JsonResponse
    {
        return response()->json(['testimonials' => Testimonial::public()]);
    }

    /** The review form a client opens from her link. */
    public function review(string $token): JsonResponse
    {
        $t = Testimonial::where('token', $token)->firstOrFail();

        return response()->json([
            'creator' => Prompts::name(),
            'name' => $t->name, 'role' => $t->role, 'company' => $t->company,
            'submitted' => $t->submitted_at !== null,
            'quote' => $t->submitted_at ? $t->quote : null,
        ]);
    }

    public function submitReview(Request $request, string $token): JsonResponse
    {
        $t = Testimonial::where('token', $token)->firstOrFail();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'role' => 'nullable|string|max:120',
            'company' => 'nullable|string|max:120',
            'quote' => 'required|string|min:10|max:1500',
            'rating' => 'nullable|integer|min:1|max:5',
            'permission' => 'required|boolean',
        ]);
        $first = $t->submitted_at === null;
        $t->update($data + ['submitted_at' => now(), 'status' => $t->status === 'approved' ? 'approved' : 'submitted']);
        if ($first) {
            AgentEvent::log('lead', "{$data['name']} sent you a testimonial".($data['company'] ? " ({$data['company']})" : '').'. Approve it in Website → Testimonials.');
            Engine::fire('testimonial', ['event' => 'testimonial', 'author' => trim($data['name'].($data['company'] ? " ({$data['company']})" : '')), 'text' => $data['quote'],
                'platform' => null, 'permission' => $data['permission']], "testimonial:{$t->id}");
        }

        return response()->json(['message' => 'Thank you! Your words mean a lot.']);
    }

    /** What the opt-in shows (nothing when it's off; the download link only comes after signing up). */
    public function magnet(): JsonResponse
    {
        $m = self::magnetConfig();

        return response()->json($m['enabled'] ? array_intersect_key($m, array_flip(['enabled', 'title', 'description', 'button'])) : ['enabled' => false]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:190',
            'name' => 'nullable|string|max:120',
            'website' => 'nullable|string|max:200', // honeypot: people never see it
        ] + Attribution::rules());
        $m = self::magnetConfig();
        $ok = ['message' => 'You’re in! Here’s your download.', 'delivery_url' => $m['delivery_url'] ?: null, 'delivery_label' => $m['delivery_label']];
        if (! empty($data['website'])) {
            return response()->json($ok); // a bot: say yes, keep nothing
        }
        $from = Attribution::resolve($data['attribution'] ?? null, $request->getHost());
        $sub = Subscriber::firstOrNew(['email' => strtolower(trim($data['email']))]);
        $sub->fill(['name' => $data['name'] ?? $sub->name, 'magnet' => $m['title'], 'unsubscribed_at' => null]);
        $new = ! $sub->exists;
        if ($new) {
            $sub->fill(['source' => $from['source'], 'attribution' => $from['attribution']]);
        }
        $sub->save();
        if ($new) {
            Engine::fire('subscriber', ['event' => 'subscriber', 'author' => $sub->email, 'text' => (string) $sub->name, 'source' => Attribution::label($sub->source), 'platform' => null], "sub:{$sub->id}");
        }

        return response()->json($ok);
    }

    // ------------------------------------------------------------ testimonials (hers)

    public function testimonials(): JsonResponse
    {
        return response()->json(['testimonials' => Testimonial::with('deal:id,brand_name')->orderBy('position')->latest()->get()
            ->map(fn (Testimonial $t) => $t->toArray() + ['url' => $t->token ? $t->url() : null])]);
    }

    /** One she already has (an email, a LinkedIn recommendation): pasted in, approved. */
    public function storeTestimonial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'role' => 'nullable|string|max:120', 'company' => 'nullable|string|max:120',
            'quote' => 'required|string|max:1500', 'rating' => 'nullable|integer|min:1|max:5', 'deal_id' => 'nullable|exists:deals,id',
        ]);
        $t = Testimonial::create($data + ['permission' => true, 'status' => 'approved', 'submitted_at' => now(), 'approved_at' => now()]);

        return response()->json(['testimonial' => $t], 201);
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|nullable|string|max:120', 'role' => 'sometimes|nullable|string|max:120', 'company' => 'sometimes|nullable|string|max:120',
            'quote' => 'sometimes|nullable|string|max:1500', 'rating' => 'sometimes|nullable|integer|min:1|max:5',
            'status' => ['sometimes', Rule::in(Testimonial::STATUSES)], 'position' => 'sometimes|integer|min:0|max:999', 'permission' => 'sometimes|boolean',
        ]);
        if (($data['status'] ?? null) === 'approved' && ! $testimonial->approved_at) {
            $data['approved_at'] = now();
        }
        $testimonial->update($data);

        return response()->json(['testimonial' => $testimonial->fresh(), 'public' => count(Testimonial::public())]);
    }

    public function destroyTestimonial(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return response()->json(['message' => 'Testimonial deleted.']);
    }

    /** The client's private review link and a thank-you email to send it with. */
    public function requestTestimonial(Deal $deal, Llm $llm): JsonResponse
    {
        $t = Testimonial::requestFor($deal);
        $draft = $deal->stage === 'paid' ? FollowUps::draft($deal, $llm) : [
            'subject' => 'A quick favour', 'ai' => false,
            'body' => 'Hi '.(explode(' ', (string) $deal->contact_name)[0] ?: 'there').",\n\nWould you share a few words about working together? It takes two minutes:\n{$t->url()}\n\nThank you!\n".explode(' ', Prompts::name())[0],
        ];

        return response()->json(['url' => $t->url(), 'draft' => $draft, 'to' => $deal->contact_email]);
    }

    // ------------------------------------------------------------ subscribers (hers)

    public function subscribers(): JsonResponse
    {
        $active = Subscriber::whereNull('unsubscribed_at');

        return response()->json([
            'total' => (clone $active)->count(),
            'last_30' => (clone $active)->where('created_at', '>=', now()->subDays(30))->count(),
            'by_source' => (clone $active)->selectRaw('source, count(*) as n')->groupBy('source')->orderByDesc('n')->get()
                ->map(fn ($r) => ['source' => $r->source, 'label' => Attribution::label($r->source), 'count' => (int) $r->n]),
            'recent' => (clone $active)->latest()->take(50)->get(['id', 'email', 'name', 'source', 'magnet', 'created_at']),
            'magnet' => self::magnetConfig(),
        ]);
    }

    /** CSV for her email tool (Kit, Beehiiv, Substack import…). */
    public function exportSubscribers(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'name', 'source', 'signed_up_for', 'signed_up_at']);
            Subscriber::whereNull('unsubscribed_at')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, $s->name, $s->source, $s->magnet, $s->created_at?->toIso8601String()]);
                }
            });
            fclose($out);
        }, 'subscribers-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function destroySubscriber(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();

        return response()->json(['message' => 'Removed.']);
    }

    public function saveMagnet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:400',
            'button' => 'required|string|max:60',
            'delivery_url' => 'nullable|url|max:500',
            'delivery_label' => 'nullable|string|max:60',
        ]);
        if ($data['enabled'] && empty($data['delivery_url'])) {
            return response()->json(['message' => 'Add the link to your download first (a Notion page, PDF or Drive link).', 'errors' => ['delivery_url' => ['Required to turn it on.']]], 422);
        }
        Setting::put(self::MAGNET, json_encode(array_merge(self::MAGNET_DEFAULTS, array_filter($data, fn ($v) => $v !== null))));

        return response()->json(['magnet' => self::magnetConfig(), 'message' => 'Saved.']);
    }

    public static function magnetConfig(): array
    {
        $saved = json_decode((string) Setting::get(self::MAGNET, '{}'), true);

        return array_merge(self::MAGNET_DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::MAGNET_DEFAULTS) : []);
    }

    // ------------------------------------------------------------ inbox lead → deal

    /** A comment or DM tagged as a lead becomes an inquiry in Deals, credited to that network and post. */
    public function inboxToDeal(Request $request, EngagementItem $item): JsonResponse
    {
        if ($item->deal_id && ($deal = Deal::find($item->deal_id))) {
            return response()->json(['message' => 'Already in your deals.', 'deal' => $deal]);
        }
        $data = $request->validate(['brand_name' => 'nullable|string|max:150', 'rate' => 'nullable|numeric|min:0']);
        $who = $item->author ? "@{$item->author}" : 'Someone';
        $deal = Deal::create([
            'brand_name' => ($data['brand_name'] ?? null) ?: "{$who} ({$item->platform})",
            'contact_name' => $who,
            'stage' => 'inquiry',
            'rate' => (float) ($data['rate'] ?? 0),
            'currency' => 'USD',
            'payment_status' => 'pending',
            'source' => isset(Attribution::LABELS[$item->platform]) ? $item->platform : 'other',
            'attribution' => array_filter(['item' => $item->social_post_id ? "post:{$item->social_post_id}" : null, 'inbox' => (string) $item->id]) ?: null,
            'notes' => trim("From your inbox ({$item->platform} ".($item->kind === 'dm' ? 'DM' : 'comment').", {$item->received_at?->toDateString()}):\n\"".Str::limit((string) $item->text, 1500).'"'),
        ]);
        $item->forceFill(['deal_id' => $deal->id])->save();

        return response()->json(['message' => 'Added to Deals as an inquiry.', 'deal' => $deal], 201);
    }
}
