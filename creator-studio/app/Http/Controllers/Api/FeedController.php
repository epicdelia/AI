<?php

namespace App\Http\Controllers\Api;

use App\Domains\Feeds\FeedReader;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** News & blog feeds: watch them, refresh them, and let their items feed the idea generator. */
class FeedController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->state());
    }

    public function store(Request $request, FeedReader $reader): JsonResponse
    {
        $url = trim((string) $request->validate(['url' => 'required|string|max:500'])['url']);
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }
        if (Feed::where('url', $url)->exists()) {
            return response()->json(['error' => 'That feed is already on your list.'], 422);
        }

        // Check it parses before saving, so typos don't become dead feeds; one download serves both.
        try {
            $parsed = $reader->parse($url);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $feed = Feed::create(['url' => $url, 'title' => $parsed['title']]);
        $new = $reader->refresh($feed, $parsed);

        return response()->json(['message' => "Added “{$feed->title}” with {$new} item(s)."] + $this->state());
    }

    public function toggle(Feed $feed): JsonResponse
    {
        $feed->update(['active' => ! $feed->active]);

        return response()->json(['feed' => $feed]);
    }

    public function destroy(Feed $feed): JsonResponse
    {
        $feed->delete();

        return response()->json(['message' => 'Feed removed.']);
    }

    public function refresh(FeedReader $reader): JsonResponse
    {
        @set_time_limit(180);
        $feeds = Feed::where('active', true)->get();
        if ($feeds->isEmpty()) {
            return response()->json(['error' => 'Add an active feed first.'], 400);
        }
        $new = $feeds->sum(fn (Feed $f) => $reader->refresh($f));
        $failed = $feeds->filter(fn (Feed $f) => $f->fresh()->last_error)->count();

        return response()->json([
            'message' => "Feeds refreshed: {$new} new item(s)".($failed ? ", {$failed} feed(s) failed" : '').'.',
        ] + $this->state());
    }

    public function destroyItem(FeedItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(['message' => 'Dismissed.']);
    }

    private function state(): array
    {
        return [
            'feeds' => Feed::withCount('items')->orderBy('title')->get(),
            'items' => FeedItem::with('feed:id,title')->orderByDesc('published_at')->orderByDesc('id')->take(60)->get(),
            'fresh_count' => FeedItem::where('used', false)->count(),
        ];
    }
}
