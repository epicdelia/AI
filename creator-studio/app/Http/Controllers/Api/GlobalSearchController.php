<?php

namespace App\Http\Controllers\Api;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Toolkit\Models\Document;
use App\Domains\Toolkit\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Script;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ctrl+K search, ported from MagicAI's GlobalSearchService: the same grouped result shape
 * ({title, items: [{label, context, url, icon}]}) and per-group cap, over this studio's
 * pages, settings, scripts, ideas, Library, chats and Toolkit tools.
 */
class GlobalSearchController extends Controller
{
    private const MAX_PER_GROUP = 5;

    private const PAGES = [
        ['Dashboard', '/', 'home', 'today overview streak'],
        ['Dictate', '/flow', 'mic', 'voice dictation note speak record'],
        ['Breakouts', '/breakouts', 'trend', 'competitor channels outliers scan news feeds rss'],
        ['Ideas', '/ideas', 'bulb', 'generate brief pick'],
        ['Scripts', '/scripts', 'doc', 'write draft editor'],
        ['My channel', '/channel', 'users', 'my videos performance median comments viewers questions audience'],
        ['Calendar', '/calendar', 'calendar', 'schedule plan week posts social content calendar'],
        ['Repurpose', '/repurpose', 'repeat', 'turn script into posts linkedin instagram x thread carousel reel short'],
        ['LinkedIn', '/linkedin', 'briefcase', 'linkedin posts carousel document'],
        ['Instagram', '/instagram', 'camera', 'instagram carousel reel caption hashtags story'],
        ['X', '/x', 'xmark', 'x twitter thread tweet post'],
        ['TikTok', '/tiktok', 'music', 'tiktok video photo carousel caption'],
        ['Threads', '/threads', 'at', 'threads meta post thread'],
        ['Weekly goals', '/goals', 'target', 'scoreboard mentor check-in plan'],
        ['Toolkit', '/tools', 'sparkle', 'titles thumbnail description tags captions templates'],
        ['Producer chat', '/chat', 'chat', 'assistant brainstorm ask'],
        ['Library', '/library', 'book', 'saved documents upload pdf'],
        ['Settings', '/settings', 'gear', 'keys profile voice'],
    ];

    private const SETTINGS = [
        ['AI engine & model', 'openai anthropic claude gemini key provider model'],
        ['Brand voice', 'tone language instructions voice excerpts'],
        ['YouTube & dictation keys', 'youtube api assemblyai transcription whisper'],
        ['Web search', 'serper perplexity fact-check'],
        ['Memory', 'embeddings search past work index'],
        ['Automation', 'schedule cron scan check-in'],
        ['Breakout research', 'lookback outlier score min views'],
        ['Goals & mentor', 'commitment baseline timezone'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => 'required|string|max:100'])['q']);
        $lower = mb_strtolower($q);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $groups = [];

        $pages = collect(self::PAGES)->filter(fn ($p) => str_contains(mb_strtolower($p[0].' '.$p[3]), $lower))
            ->map(fn ($p) => ['label' => $p[0], 'context' => 'Page', 'url' => $p[1], 'icon' => $p[2]]);
        $groups[] = ['title' => 'Pages', 'items' => $pages->take(self::MAX_PER_GROUP)->values()];

        $settings = collect(self::SETTINGS)->filter(fn ($s) => str_contains(mb_strtolower($s[0].' '.$s[1]), $lower))
            ->map(fn ($s) => ['label' => $s[0], 'context' => 'Settings', 'url' => '/settings', 'icon' => 'gear']);
        $groups[] = ['title' => 'Settings', 'items' => $settings->take(self::MAX_PER_GROUP)->values()];

        $groups[] = ['title' => 'Scripts', 'items' => Script::query()
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like))
            ->latest('updated_at')->limit(self::MAX_PER_GROUP)->get(['id', 'title', 'status'])
            ->map(fn ($s) => ['label' => $s->title, 'context' => 'Script · '.$s->status, 'url' => "/scripts/{$s->id}", 'icon' => 'doc'])];

        $groups[] = ['title' => 'Ideas', 'items' => Idea::query()
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('premise', 'like', $like)->orWhere('notes', 'like', $like))
            ->latest()->limit(self::MAX_PER_GROUP)->get(['id', 'title', 'status'])
            ->map(fn ($i) => ['label' => $i->title, 'context' => 'Idea · '.$i->status, 'url' => '/ideas', 'icon' => 'bulb'])];

        $groups[] = ['title' => 'Library', 'items' => Document::query()
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like))
            ->latest('updated_at')->limit(self::MAX_PER_GROUP)->get(['id', 'title', 'tool'])
            ->map(fn ($d) => ['label' => $d->title, 'context' => 'Library'.($d->tool ? ' · '.str_replace(['tool:', 'youtube:', 'upload:'], ['', 'youtube ', 'upload '], $d->tool) : ''), 'url' => '/library?doc='.$d->id, 'icon' => 'book'])];

        $groups[] = ['title' => 'Chats', 'items' => Conversation::query()
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhereHas('messages', fn ($m) => $m->where('content', 'like', $like)))
            ->latest('updated_at')->limit(self::MAX_PER_GROUP)->get(['id', 'title'])
            ->map(fn ($c) => ['label' => $c->title, 'context' => 'Producer chat', 'url' => "/chat/{$c->id}", 'icon' => 'chat'])];

        $groups[] = ['title' => 'Toolkit', 'items' => collect(ToolRegistry::all())
            ->filter(fn ($t) => str_contains(mb_strtolower($t['name'].' '.($t['description'] ?? '')), $lower))
            ->take(self::MAX_PER_GROUP)
            ->map(fn ($t) => ['label' => $t['name'], 'context' => 'Tool', 'url' => "/tools/{$t['key']}", 'icon' => 'sparkle'])->values()];

        return response()->json(['groups' => array_values(array_filter($groups, fn ($g) => count($g['items']) > 0))]);
    }
}
