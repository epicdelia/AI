<?php

namespace App\Domains\Landing;

use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Outlier;
use App\Models\Script;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which content pays: website leads and revenue per channel, and per video or post when the
 * visitor came through a tracked link (?ref=yt-<id> / post-<id>). Only her own numbers.
 */
class RevenueAttribution
{
    public static function summary(int $days = 90): array
    {
        $deals = Deal::whereNotNull('source')->where('created_at', '>=', now()->subDays($days))->get();
        $money = fn (Collection $c) => [
            'leads' => $c->count(),
            'pipeline' => round((float) $c->whereNotIn('stage', Deal::NOT_PIPELINE)->sum('rate'), 2),
            'paid' => round((float) $c->where('payment_status', 'paid')->sum('rate'), 2),
            'won' => $c->where('payment_status', 'paid')->count(),
        ];

        $channels = $deals->groupBy('source')->map(fn ($c, $src) => ['source' => $src, 'label' => Attribution::label($src)] + $money($c))
            ->sortByDesc(fn ($r) => [$r['paid'], $r['pipeline'], $r['leads']])->values();

        $items = $deals->filter(fn ($d) => ! empty($d->attribution['item']))->groupBy(fn ($d) => $d->attribution['item'])
            ->map(fn ($c, $key) => self::describe($key) + $money($c))
            ->sortByDesc(fn ($r) => [$r['paid'], $r['pipeline'], $r['leads']])->values();

        return [
            'days' => $days,
            'totals' => $money($deals),
            'channels' => $channels,
            'items' => $items->take(15)->values(),
            'untracked' => $deals->filter(fn ($d) => empty($d->attribution['item']))->count(),
        ];
    }

    /** A content key as something she recognises: the video title or the post's first line. */
    public static function describe(string $key): array
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');
        if ($type === 'yt') {
            $title = DB::table('video_stats')->where('video_id', $id)->orderByDesc('captured_on')->value('title')
                ?? Script::where('youtube_url', 'like', "%{$id}%")->value('title')
                ?? Outlier::where('video_id', $id)->value('title');

            return ['key' => $key, 'type' => 'video', 'platform' => 'youtube', 'title' => $title ?: "YouTube video {$id}", 'url' => "https://youtu.be/{$id}", 'link' => null];
        }
        $post = SocialPost::find((int) $id);

        return ['key' => $key, 'type' => 'post', 'platform' => $post?->platform, 'title' => $post ? mb_substr($post->title ?: str_replace("\n", ' ', (string) $post->body), 0, 90) : "Post #{$id}",
            'url' => $post?->url, 'link' => $post ? "/posts/{$post->id}" : null];
    }
}
