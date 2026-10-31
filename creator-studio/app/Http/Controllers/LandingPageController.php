<?php

namespace App\Http\Controllers;

use App\Domains\Landing\ClientJourney;
use App\Domains\Landing\LandingFeed;
use App\Domains\Media\MediaFile;
use App\Domains\Social\Models\SocialPost;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Her public Tech Unicorn landing page lives at "/" (the SPA, signed out). This controller serves
 * its content feed, counts clicks on it, and lets her curate it from the dashboard.
 */
class LandingPageController extends Controller
{
    /** Old addresses (/techunicorn, /landing-page) point to the one landing page, keeping ?book= etc. */
    public function show(Request $request): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect('/'.($query ? '?'.$query : ''), 301);
    }

    /** "Content & Social Channels": YouTube, Instagram, TikTok, X and Threads. */
    public function socialFeed(): JsonResponse
    {
        return response()->json(LandingFeed::get())->header('Cache-Control', 'public, max-age=300');
    }

    /** Just the YouTube part (kept for older pages). */
    public function youtubeVideos(): JsonResponse
    {
        $feed = LandingFeed::get();

        return response()->json(['channel' => $feed['channel'], 'source' => $feed['sources']['youtube'], 'videos' => $feed['platforms']['youtube']]);
    }

    /** "How it works": whether a build slot is open (counts only, set from her dashboard). */
    public function availability(): JsonResponse
    {
        $a = ClientJourney::availability();

        return response()->json($a['show'] ? ['show' => true, 'status' => $a['status'], 'open' => $a['open'], 'label' => $a['label']] : ['show' => false]);
    }

    /** A visitor opened an item (sent with sendBeacon, so the link itself is never delayed). */
    public function click(Request $request): Response
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:/^(yt:[\w-]{11}|post:\d{1,10}|profile:[a-z]{1,12})$/'],
            'platform' => 'required|string|in:'.implode(',', array_keys(LandingFeed::PLATFORMS)),
        ]);
        LandingFeed::recordClick($data['key'], $data['platform']);

        return response()->noContent();
    }

    /** First image of a post shown on the landing page (the post is already public on the network). */
    public function thumb(SocialPost $post): Response
    {
        $visible = collect(LandingFeed::get()['platforms'])->flatten(1)->contains('key', 'post:'.$post->id);
        $image = collect($post->media ?? [])->first(fn ($m) => str_starts_with((string) ($m['mime'] ?? ''), 'image/'));
        $file = $visible && $image ? MediaFile::find($image['id']) : null;
        abort_unless($file && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, null, ['Content-Type' => $file->mime, 'Cache-Control' => 'public, max-age=3600']);
    }

    // ---------------------------------------------------------------- her dashboard

    /** Everything the feed could show (hidden items flagged), with clicks per item. */
    public function content(): JsonResponse
    {
        $feed = LandingFeed::build(withHidden: true);
        $clicks30 = LandingFeed::clicks(30);
        foreach ($feed['platforms'] as $platform => $items) {
            foreach ($items as $i => $item) {
                $feed['platforms'][$platform][$i]['clicks_30d'] = $clicks30[$item['key']] ?? 0;
            }
        }

        return response()->json($feed + ['summary' => self::summary()]);
    }

    /** Pin or hide items; the public page updates straight away. */
    public function curate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden' => 'present|array|max:200',
            'hidden.*' => ['string', 'regex:/^(yt:[\w-]{11}|post:\d{1,10})$/'],
            'pinned' => 'present|array|max:50',
            'pinned.*' => ['string', 'regex:/^(yt:[\w-]{11}|post:\d{1,10})$/'],
        ]);
        Setting::put('landing_hidden', json_encode(array_values(array_unique($data['hidden']))));
        Setting::put('landing_pinned', json_encode(array_values(array_unique(array_diff($data['pinned'], $data['hidden'])))));
        LandingFeed::refresh();

        return $this->content();
    }

    /** How many builds she takes on at once (0 hides availability on the landing page). */
    public function saveCapacity(Request $request): JsonResponse
    {
        $data = $request->validate(['capacity' => 'required|integer|min:0|max:10']);
        Setting::put('build_capacity', (string) $data['capacity']);

        return response()->json(ClientJourney::summary());
    }

    public function refresh(): JsonResponse
    {
        LandingFeed::refresh();

        return $this->content();
    }

    /** The dashboard card: what's live per network and what visitors open. */
    public static function summary(): array
    {
        $feed = LandingFeed::get();
        $week = LandingFeed::clicks(7);
        $items = collect($feed['platforms'])->flatMap(fn ($list, $platform) => collect($list)->map(fn ($i) => $i + ['platform' => $platform]));
        $top = $items->map(fn ($i) => ['key' => $i['key'], 'platform' => $i['platform'], 'title' => $i['title'], 'url' => $i['url'], 'clicks' => $week[$i['key']] ?? 0])
            ->filter(fn ($i) => $i['clicks'] > 0)->sortByDesc('clicks')->take(3)->values();

        return [
            'updated_at' => $feed['updated_at'],
            'clicks_7d' => array_sum($week),
            'top' => $top,
            'sources' => $feed['sources'],
            'counts' => array_map('count', $feed['platforms']),
            'headline' => $feed['headline'],
        ];
    }
}
