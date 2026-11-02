<?php

namespace App\Domains\Engagement;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Engagement\Models\EngagementRule;
use App\Domains\Social\Connect\ConnectException;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Workflows\Engine;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Comment & DM automation. Every few minutes: pull new comments and DMs from connected
 * accounts, run her rules (keyword replies, "DM me the link", answer questions), and either
 * send or leave a draft in the Inbox. AI replies are written in her voice and learn from the
 * replies she rewrote herself.
 */
class Engagement
{
    /**
     * Comments with links, crypto or "DM me" bait never get an automatic reply.
     */
    public const SPAM = '~https?://|www\.|t\.me/|whatsapp|telegram|crypto|bitcoin|forex|promote it on|dm (me|us) for|collab\?? ?dm~i';

    public function __construct(
        private Llm $llm
    ) {
    }

    /**
     * @return array{new: int, sent: int, drafted: int, errors: array<string, string>}
     */
    public function poll(): array
    {
        $new = $sent = $drafted = 0;
        $errors = [];
        foreach (SocialAccount::where('status', 'connected')->get() as $account) {
            $p = Connections::provider($account->platform);
            if (!$p->engagement()) {
                continue;
            }
            $key = "engage_since_{$account->platform}";
            // First poll looks back 2 days, so turning this on doesn't answer a month of old comments.
            $since = CarbonImmutable::parse(Setting::get($key) ?: now()->subDays(2)->toIso8601String())->subMinutes(10);

            try {
                $items = $p->inbox($account, Connections::token($account), $since);
                Setting::put($key, now()->toIso8601String());
            } catch (Throwable $e) {
                $errors[$account->platform] = $e->getMessage();

                continue;
            }
            foreach ($items as $row) {
                $item = EngagementItem::firstOrCreate(
                    [
                        'platform' => $account->platform,
                        'kind' => $row['kind'],
                        'external_id' => $row['external_id'],
                    ],
                    [
                        'thread_id' => $row['thread_id'] ?? null,
                        'author' => $row['author'] ?? null,
                        'author_id' => $row['author_id'] ?? null,
                        'text' => mb_substr((string)$row['text'], 0, 4000),
                        'received_at' => $this->date($row['received_at'] ?? null),
                        'intent' => self::intent((string)$row['text']),
                        'social_post_id' => !empty($row['thread_id']) ? SocialPost::where('platform', $account->platform)->where('external_id', $row['thread_id'])->value('id') : null,
                    ],
                );
                if (!$item->wasRecentlyCreated) {
                    continue;
                }
                $new++;
                // Workflows first; a comment a workflow picked up isn't also answered by a rule.
                if (Engine::dispatch($item->kind === 'dm' ? 'dm' : 'comment', Engine::itemContext($item), "item:{$item->id}")) {
                    continue;
                }
                $result = $this->applyRules($item);
                $sent += $result === 'replied' ? 1 : 0;
                $drafted += $result === 'drafted' ? 1 : 0;
            }
        }
        app(Engine::class)->drain();
        if ($new) {
            $waiting = EngagementItem::whereIn('status', ['new', 'drafted'])->count();
            AgentEvent::log('comment', "{$new} new comment(s)/DM(s)" . ($sent ? ", {$sent} answered by your automations" : '')
                . ($waiting ? "; {$waiting} waiting in your Inbox." : '.'));
        }

        return [
            'new' => $new,
            'sent' => $sent,
            'drafted' => $drafted,
            'errors' => $errors,
        ];
    }

    /**
     * First matching rule wins. Returns 'replied', 'drafted' or null.
     */
    public function applyRules(EngagementItem $item): ?string
    {
        if ($item->intent === 'spam') {
            return null;
        }
        foreach (EngagementRule::where('active', true)->orderBy('id')->get() as $rule) {
            if (!$rule->matches($item)) {
                continue;
            }
            $recent = EngagementItem::where('engagement_rule_id', $rule->id)->where('replied_by', 'rule')->where('replied_at', '>=', now()->subHour())->count();
            $reply = match ($rule->reply_mode) {
                'template' => $rule->fillTemplate($rule->reply_template, $item),
                'ai' => $this->draft($item, $rule->instructions),
                default => '',
            };
            $item->update([
                'engagement_rule_id' => $rule->id,
                'reply_draft' => $reply ?: null,
                'status' => $reply || $rule->dm_text ? 'drafted' : 'new',
            ]);
            $rule->increment('fired');
            if ($rule->auto_send && $recent < $rule->max_per_hour) {
                try {
                    $this->send($item, $reply ?: null, $rule->dm_text ? $rule->fillTemplate($rule->dm_text, $item) : null, 'rule');

                    return 'replied';
                } catch (Throwable $e) {
                    $item->update([
                        'status' => 'failed',
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    return null;
                }
            }

            return 'drafted';
        }

        return null;
    }

    /**
     * Reply publicly and/or by DM. $by: her | rule | ai.
     */
    public function send(EngagementItem $item, ?string $reply, ?string $dm = null, string $by = 'her'): void
    {
        $account = SocialAccount::where('platform', $item->platform)->where('status', 'connected')->first()
            ?? throw new ConnectException(Platforms::PLATFORMS[$item->platform]['label'] . ' isn’t connected.');
        $p = Connections::provider($item->platform);
        $token = Connections::token($account);
        if ($reply !== null && trim($reply) !== '') {
            $p->reply($account, $token, $item->forProvider(), trim($reply));
        }
        if ($dm !== null && trim($dm) !== '' && $item->kind === 'comment') {
            $p->privateReply($account, $token, $item->forProvider(), trim($dm));
        }
        // A second send (a workflow's reply, then its DM) adds to what was already sent.
        $sent = array_filter([$reply !== null && trim($reply) !== '' ? trim($reply) : null, $dm !== null && trim($dm) !== '' ? '[DM] ' . trim($dm) : null]);
        $before = $item->status === 'replied' && $item->reply ? $item->reply . "\n\n" : '';
        $item->update([
            'status' => 'replied',
            'reply' => $before . implode("\n\n", $sent),
            'replied_by' => $by,
            'replied_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * An AI reply in her voice, guided by the rule's instructions and her own past replies.
     */
    public function draft(EngagementItem $item, ?string $instructions = null): string
    {
        $examples = EngagementItem::where('replied_by', 'her')->whereNotNull('reply')->latest('replied_at')->limit(8)->get()
            ->map(fn ($i) => 'Them: ' . mb_substr(str_replace("\n", ' ', $i->text), 0, 200) . "\nHer: " . mb_substr(str_replace("\n", ' ', $i->reply), 0, 300))->implode("\n\n");
        $post = $item->post ? mb_substr(str_replace("\n", ' ', $item->post->asText()), 0, 600) : null;
        $where = $item->kind === 'dm' ? 'a DM' : 'a comment on her ' . Platforms::PLATFORMS[$item->platform]['label'] . ' post';

        $text = $this->llm->as('engagement')->chat(
            'You write replies for ' . Prompts::name() . " to {$where}. She is a creator; the reply goes out under her name.\n\n"
            . Prompts::profile() . "\n\n" . Prompts::voice() . "\n\n"
            . "Rules: 1 to 3 short sentences, warm and specific to what they said. Answer questions plainly; if the answer isn't known from the post, say she'll look into it rather than guessing. "
            . 'Never promise a video, a date, a giveaway, prices or a collab. No links unless the instructions give one. At most one emoji. Reply with the text only.'
            . ($instructions ? "\n\nHer instructions for these replies: {$instructions}" : '')
            . ($examples ? "\n\nHow she actually replies (match this style):\n{$examples}" : ''),
            ($post ? "Her post: {$post}\n\n" : '') . 'From ' . ($item->author ? '@' . $item->author : 'someone') . ": {$item->text}",
        );

        return trim(trim($text), "\"' \n");
    }

    /**
     * Cheap intent tag for the inbox filters (the AI isn't needed for this).
     */
    public static function intent(string $text): string
    {
        $t = mb_strtolower($text);

        return match (true) {
            (bool)preg_match(self::SPAM, $t) => 'spam',
            // a question about working with her is a lead first
            (bool)preg_match('~\b(price|cost|buy|course|coaching|hire|work with you|sponsor|partnership|rates?)\b~u', $t) => 'lead',
            str_contains($t, '?') || (bool)preg_match('~^(how|what|where|which|why|when|can you|do you|is there|does)\b~u', $t) => 'question',
            (bool)preg_match('~\b(love|great|amazing|thank|awesome|helpful|best)\b|❤|🔥|😍|🙌~u', $t) => 'praise',
            default => 'other',
        };
    }

    private function date(?string $v): ?CarbonImmutable
    {
        try {
            return $v ? CarbonImmutable::parse($v)->utc() : now()->toImmutable();
        } catch (Throwable) {
            return now()->toImmutable();
        }
    }
}
