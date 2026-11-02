<?php

namespace App\Domains\Agents;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use Throwable;

/**
 * Sends approved agent posts at their time, on networks that allow it (LinkedIn, Threads, X)
 * and that she has connected. Everything else stays in the "due now" queue for her to post by
 * hand. A failed post goes back to a draft with the reason, so it never retries in a loop.
 */
class Publisher
{
    /**
     * @return array{published: int, failed: int}
     */
    public static function due(): array
    {
        $published = $failed = 0;
        $accounts = SocialAccount::where('status', 'connected')->get()->keyBy('platform');
        $posts = SocialPost::where('status', 'scheduled')->where('approval', 'approved')
            ->where(fn ($q) => $q->whereNotNull('social_agent_id')->orWhere('source', 'workflow'))
            ->where('scheduled_for', '<=', now())->where('scheduled_for', '>=', now()->subHours(6)) // never fire a stale backlog
            ->orderBy('scheduled_for')->limit(20)->get();

        foreach ($posts as $post) {
            $account = $accounts[$post->platform] ?? null;
            $provider = isset(Connections::PROVIDERS[$post->platform]) ? Connections::provider($post->platform) : null;
            if (!$account || !$provider?->canPublish()) {
                continue; // she posts this one herself (the due queue reminds her)
            }

            try {
                $r = self::publish($post, $account);
                AgentEvent::log(
                    'published',
                    'Published on ' . Platforms::PLATFORMS[$post->platform]['label'] . ': ' . mb_substr((string)($post->title ?: $post->body), 0, 90),
                    $post->social_agent_id,
                    $post->id,
                    $post->platform
                );
                $published++;
            } catch (Throwable $e) {
                $post->update([
                    'status' => 'draft',
                    'approval' => 'pending',
                    'publish_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                AgentEvent::log(
                    'failed',
                    'Couldn’t publish on ' . Platforms::PLATFORMS[$post->platform]['label'] . ': ' . $e->getMessage() . ' It’s back in your approvals.',
                    $post->social_agent_id,
                    $post->id,
                    $post->platform
                );
                $failed++;
            }
        }

        return [
            'published' => $published,
            'failed' => $failed,
        ];
    }

    /**
     * Publish one post now (also used by "Publish now").
     */
    public static function publish(SocialPost $post, ?SocialAccount $account = null): array
    {
        $account ??= SocialAccount::where('platform', $post->platform)->firstOrFail();
        $provider = Connections::provider($post->platform);
        $r = $provider->publish($account, Connections::token($account), $post);
        $post->update([
            'status' => 'posted',
            'posted_at' => now(),
            'external_id' => $r['external_id'] ?: null,
            'url' => $r['url'] ?? $post->url,
            'publish_error' => null,
        ]);

        return $r;
    }
}
