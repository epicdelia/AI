<?php

namespace App\Http\Controllers;

use App\Domains\Landing\About;
use App\Domains\Landing\Audience;
use App\Domains\Landing\ClientJourney;
use App\Domains\Landing\LandingFeed;
use App\Domains\Landing\Talk;
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
        return response()->json(LandingFeed::get())->header('Cache-Control', 'public, max-age=60');
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

    /** "01: About": her words plus live facts (audience, latest talk, build-slot availability). */
    public function about(): JsonResponse
    {
        return response()->json(About::publicSection())->header('Cache-Control', 'public, max-age=60');
    }

    /** The dashboard's "About you" editor. */
    public function aboutEdit(): JsonResponse
    {
        $about = About::get();

        return response()->json(['about' => $about + ['portrait_url' => About::portraitUrl($about)], 'preview' => About::publicSection()]);
    }

    public function aboutSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'headline' => 'required|string|max:120',
            'bio' => 'present|array|max:3',
            'bio.*' => 'nullable|string|max:900',
            'thesis' => 'nullable|string|max:160',
            'thesis_detail' => 'nullable|string|max:400',
            'now' => 'nullable|string|max:120',
            'location' => 'nullable|string|max:80',
            'countries' => 'required|integer|min:0|max:250',
            'story_url' => 'nullable|url:http,https|max:300',
            'milestones' => 'present|array|max:6',
            'milestones.*.label' => 'nullable|string|max:60',
            'milestones.*.detail' => 'nullable|string|max:200',
        ]);
        $data['bio'] = array_values(array_map(fn ($p) => trim((string) $p), $data['bio']));
        $data['milestones'] = array_values(array_map(fn ($m) => ['label' => trim((string) ($m['label'] ?? '')), 'detail' => trim((string) ($m['detail'] ?? ''))], $data['milestones']));
        foreach (['thesis', 'thesis_detail', 'now', 'location', 'story_url'] as $k) {
            $data[$k] = trim((string) ($data[$k] ?? ''));
        }
        About::save($data);

        return $this->aboutEdit();
    }

    /** Her own portrait for the About section (JPEG, PNG or WebP up to 8 MB). */
    public function aboutPortrait(Request $request): JsonResponse
    {
        $file = $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192'])['image'];
        $old = About::get()['portrait'];
        $path = $file->storeAs('about', \Illuminate\Support\Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'), 'local');
        About::save(['portrait' => $path]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return $this->aboutEdit();
    }

    public function aboutPortraitReset(): JsonResponse
    {
        $old = About::get()['portrait'];
        About::save(['portrait' => null]);
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return $this->aboutEdit();
    }

    public function aboutPortraitFile(): Response
    {
        $path = About::get()['portrait'];
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    /** "On Stage": her talks (featured, upcoming, past), stats from that list, and her talk topics. */
    public function talks(): JsonResponse
    {
        return response()->json(Talk::publicSection())->header('Cache-Control', 'public, max-age=60');
    }

    /** An uploaded stage photo, only for a talk that's on the page. */
    public function talkImage(Talk $talk): Response
    {
        abort_unless($talk->visible && $talk->image && ! str_starts_with($talk->image, '/') && Storage::disk('local')->exists($talk->image), 404);

        return Storage::disk('local')->response($talk->image, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    /** Her dashboard's "Speaking": next talk, stats, and speaking requests from the website. */
    public static function speaking(): array
    {
        $talks = Talk::where('visible', true)->get();
        $requests = Talk::requests();

        return [
            'next' => $talks->filter->upcoming->sortBy('date')->first(),
            'stats' => Talk::stats($talks),
            'requests' => $requests->count(),
            'latest_requests' => $requests->sortByDesc('created_at')->take(3)->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->contact_name ?: $d->brand_name, 'company' => $d->brand_name,
                'when' => $d->key_talking_points, 'created_at' => $d->created_at,
            ])->values(),
        ];
    }

    /** A visitor opened an item (sent with sendBeacon, so the link itself is never delayed). */
    public function click(Request $request): Response
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:/^(yt:[\w-]{11}|post:\d{1,10}|profile:[a-z]{1,12})$/'],
            'platform' => 'required|string|in:'.implode(',', array_unique([...array_keys(LandingFeed::PLATFORMS), ...array_keys(Audience::PROFILES)])),
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

    /** Show or hide networks in "Reach & Content" (at least one stays visible). */
    public function saveReach(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden' => 'present|array|max:5',
            'hidden.*' => 'string|in:'.implode(',', array_keys(Audience::PROFILES)),
        ]);
        Setting::put('landing_reach_hidden', json_encode(array_values(array_unique($data['hidden']))));
        LandingFeed::forget();

        return response()->json(self::reach());
    }

    /** Her dashboard's "Public reach": what visitors see per network, plus profile opens in 30 days. */
    public static function reach(): array
    {
        $reach = Audience::reach();
        $clicks = LandingFeed::clicks(30);
        foreach ($reach['networks'] as $i => $n) {
            // X was "twitter" in the content tabs; both count as opening her X profile.
            $reach['networks'][$i]['opens_30d'] = ($clicks["profile:{$n['key']}"] ?? 0) + ($n['key'] === 'x' ? ($clicks['profile:twitter'] ?? 0) : 0);
        }

        return $reach;
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
