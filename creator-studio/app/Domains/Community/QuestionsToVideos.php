<?php

namespace App\Domains\Community;

use App\Domains\Community\Models\CommunityItem;
use App\Http\Controllers\Api\AuthController;
use App\Models\Idea;
use App\Models\Script;

/**
 * Members' questions as content: the most-voted ones become ideas (by hand, or as signals
 * for the idea generator), and when the video for one goes live, whoever asked hears about it.
 */
class QuestionsToVideos
{
    public static function toIdea(CommunityItem $q): Idea
    {
        abort_unless($q->type === 'question', 422, 'Only questions become ideas.');
        if ($q->idea_id && ($existing = Idea::find($q->idea_id))) {
            return $existing;
        }
        $idea = Idea::create([
            'title' => mb_substr($q->title, 0, 200),
            'premise' => $q->title,
            'why_now' => 'Asked in the community' . ($q->upvotes ? " ({$q->upvotes} votes)" : '') . '.',
            'source_url' => url('/community'),
            'status' => 'new',
        ]);
        $q->forceFill(['idea_id' => $idea->id])->save();

        return $idea;
    }

    /**
     * For the idea generator: the most-voted unanswered questions not used yet.
     *
     * @return array<int, string>
     */
    public static function signalLines(int $limit = 5): array
    {
        return CommunityItem::published()->where('type', 'question')->whereNull('idea_id')->whereNull('answered_at')
            ->where('created_at', '>=', now()->subDays(60))->orderByDesc('upvotes')->take($limit)->get()
            ->map(fn ($q) => "- \"{$q->title}\" ({$q->upvotes} votes, source_url: " . url('/community') . ')')->all();
    }

    /**
     * A script just went live: tell the members whose question it answers.
     */
    public static function notifyPublished(Script $script): int
    {
        if (!$script->idea_id || !$script->youtube_url) {
            return 0;
        }
        $sent = 0;
        CommunityItem::with('user')->where('idea_id', $script->idea_id)->whereNull('video_notified_at')->get()->each(function (CommunityItem $q) use ($script, &$sent) {
            if ($q->user && ($q->user->notify_email ?? true)) {
                AuthController::mail($q->user->email, 'Your question became a video', "Hi {$q->user->name},\n\nYou asked: “{$q->title}”\n\nThe answer is now a video:\n{$script->youtube_url}\n\nThanks for asking!");
                $sent++;
            }
            $q->forceFill(['video_notified_at' => now()])->save();
        });

        return $sent;
    }
}
