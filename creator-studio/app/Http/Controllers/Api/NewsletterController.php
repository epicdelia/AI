<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Her newsletter to the people who subscribed on her site: write in Markdown (or start from a
 * script), pick everyone or a segment (where they signed up, which lead magnet), test it,
 * send. Sending is batched (100 a minute by the scheduler), every email has a one-click
 * unsubscribe link and List-Unsubscribe headers. No open tracking; links can be /go/ links.
 */
class NewsletterController extends Controller
{
    public const BATCH = 100;

    public function index(): JsonResponse
    {
        $active = Subscriber::whereNull('unsubscribed_at');

        return response()->json([
            'campaigns' => DB::table('campaigns')->orderByDesc('id')->take(50)->get()->map(fn ($c) => (array)$c + ['segment' => json_decode((string)$c->segment, true)]),
            'subscribers' => [
                'active' => (clone $active)->count(),
                'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(),
                'sources' => (clone $active)->select('source', DB::raw('count(*) as n'))->groupBy('source')->pluck('n', 'source'),
                'magnets' => (clone $active)->whereNotNull('magnet')->select('magnet', DB::raw('count(*) as n'))->groupBy('magnet')->pluck('n', 'magnet'),
            ],
        ]);
    }

    public function save(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'subject' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'body' => [$id ? 'sometimes' : 'required', 'string', 'max:100000'],
            'segment' => 'nullable|array',
            'segment.source' => 'nullable|string|max:40',
            'segment.magnet' => 'nullable|string|max:200',
        ]);
        if (array_key_exists('segment', $data)) {
            $data['segment'] = json_encode(array_filter((array)$data['segment']));
        }
        if ($id) {
            $c = DB::table('campaigns')->where('id', $id)->first();
            abort_unless($c, 404);
            abort_unless($c->status === 'draft', 422, 'This one has been sent already.');
            DB::table('campaigns')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('campaigns')->insertGetId($data + ['status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['id' => $id] + $this->index()->getData(true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('campaigns')->where('id', $id)->where('status', 'draft')->delete();

        return $this->index();
    }

    /**
     * Start from a script: its title and the AI newsletter draft saved on it, else its body.
     */
    public function fromScript(int $script): JsonResponse
    {
        $s = \App\Models\Script::findOrFail($script);
        $body = trim((string)($s->substack ?: $s->body));
        if ($s->youtube_url) {
            $body .= "\n\n[Watch the video]({$s->youtube_url})";
        }

        return response()->json(['subject' => $s->title, 'body' => $body]);
    }

    public function test(Request $request, int $id): JsonResponse
    {
        $c = DB::table('campaigns')->where('id', $id)->first();
        abort_unless($c, 404);
        $ok = self::deliver($request->user()->email, $request->user()->name, '[Test] ' . $c->subject, $c->body, url('/'));

        return response()->json(['ok' => $ok, 'message' => $ok ? "Test sent to {$request->user()->email}." : 'The test couldn’t be sent: check the mail settings.']);
    }

    public function send(int $id): JsonResponse
    {
        $c = DB::table('campaigns')->where('id', $id)->first();
        abort_unless($c, 404);
        abort_unless($c->status === 'draft', 422, 'This one has been sent already.');
        $seg = json_decode((string)$c->segment, true) ?: [];
        $ids = Subscriber::whereNull('unsubscribed_at')
            ->when($seg['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($seg['magnet'] ?? null, fn ($q, $v) => $q->where('magnet', $v))
            ->pluck('id');
        abort_if($ids->isEmpty(), 422, 'Nobody matches this segment.');
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('campaign_sends')->insertOrIgnore($chunk->map(fn ($sid) => ['campaign_id' => $id, 'subscriber_id' => $sid])->values()->all());
        }
        DB::table('campaigns')->where('id', $id)->update(['status' => 'sending', 'total' => $ids->count(), 'updated_at' => now()]);

        return response()->json(['ok' => true, 'total' => $ids->count(), 'message' => "Sending to {$ids->count()} subscriber(s), about " . (int)ceil($ids->count() / self::BATCH) . ' minute(s).']);
    }

    /**
     * The scheduler: up to BATCH emails per run across sending campaigns. Returns how many went out.
     */
    public static function drain(): int
    {
        $sent = 0;
        foreach (DB::table('campaigns')->where('status', 'sending')->orderBy('id')->get() as $c) {
            $rows = DB::table('campaign_sends')->join('subscribers', 'subscribers.id', '=', 'campaign_sends.subscriber_id')
                ->where('campaign_sends.campaign_id', $c->id)->whereNull('campaign_sends.sent_at')->whereNull('campaign_sends.error')
                ->limit(self::BATCH - $sent)->get(['campaign_sends.id', 'subscribers.id as sid', 'subscribers.email', 'subscribers.name', 'subscribers.unsubscribed_at']);
            foreach ($rows as $r) {
                if ($r->unsubscribed_at) {
                    DB::table('campaign_sends')->where('id', $r->id)->update(['error' => 'unsubscribed']);

                    continue;
                }
                $ok = self::deliver($r->email, $r->name, $c->subject, $c->body, self::unsubscribeUrl((int)$r->sid));
                DB::table('campaign_sends')->where('id', $r->id)->update($ok ? ['sent_at' => now()] : ['error' => 'mail failed']);
                DB::table('campaigns')->where('id', $c->id)->increment($ok ? 'sent_count' : 'failed_count');
                $sent++;
            }
            $left = DB::table('campaign_sends')->where('campaign_id', $c->id)->whereNull('sent_at')->whereNull('error')->exists();
            if (!$left) {
                DB::table('campaigns')->where('id', $c->id)->update(['status' => 'sent', 'sent_at' => now()]);
            }
            if ($sent >= self::BATCH) {
                break;
            }
        }

        return $sent;
    }

    public static function unsubscribeUrl(int $subscriberId): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriberId]);
    }

    /**
     * Public (signed link): one click unsubscribes. GET shows a page, POST is the mail client's one-click.
     */
    public function unsubscribe(Request $request, int $subscriber): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        Subscriber::whereKey($subscriber)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]);
        if ($request->isMethod('post')) {
            return response()->noContent();
        }

        return response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribed</title>'
            . '<body style="font-family:system-ui,sans-serif;max-width:480px;margin:15vh auto;padding:0 16px;line-height:1.5">'
            . '<h1>You’re unsubscribed</h1><p>You won’t get the newsletter any more. Changed your mind? Sign up again on the website any time.</p></body>');
    }

    private static function deliver(string $to, ?string $name, string $subject, string $markdown, string $unsubscribe): bool
    {
        $body = str_replace('{name}', $name ?: 'there', $markdown);
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = '<!doctype html><html><body style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;max-width:640px;margin:0 auto;padding:16px;line-height:1.6;color:#1f1d22">'
            . $html . '<hr style="border:none;border-top:1px solid #ddd;margin:32px 0 12px"><p style="font-size:12px;color:#777">You get this because you subscribed on the website. '
            . '<a href="' . e($unsubscribe) . '">Unsubscribe</a></p></body></html>';
        $text = $body . "\n\n—\nUnsubscribe: {$unsubscribe}";
        try {
            $from = config('mail.from.address') ?: 'hello@example.com';
            $fromName = PortalBrandController::fromName() ?: (config('mail.from.name') ?: 'Creator Studio');
            Mail::send([], [], function ($m) use ($to, $subject, $html, $text, $unsubscribe, $from, $fromName) {
                $m->from($from, $fromName)->to($to)->subject($subject)->html($html)->text($text);
                $headers = $m->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning("Newsletter to {$to} failed: {$e->getMessage()}");

            return false;
        }
    }
}
