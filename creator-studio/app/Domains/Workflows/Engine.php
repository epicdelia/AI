<?php

namespace App\Domains\Workflows;

use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Engagement\Engagement;
use App\Domains\Engagement\Models\EngagementItem;
use App\Domains\Engagement\Models\EngagementRule;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\Platforms;
use App\Domains\Social\Repurposer;
use App\Domains\Social\SocialAnalytics;
use App\Domains\Social\SocialScheduler;
use App\Domains\Workflows\Models\Workflow;
use App\Domains\Workflows\Models\WorkflowRun;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/**
 * Runs her workflows. Events (a comment, a DM, a published post, a post taking off, the clock)
 * queue a run for every active workflow whose trigger matches; runs execute their enabled steps
 * in order. "Wait" parks a run until later, "Only continue if" can stop it. Each workflow fires
 * once per trigger (the same comment never gets two replies). Dry runs (Test) never send,
 * publish or save anything: they show what each step would do.
 */
class Engine
{
    /** Runs a workflow may start per hour, so a loop or a flood of comments can't run away. */
    public const MAX_RUNS_PER_HOUR = 60;

    private static bool $drainScheduled = false;

    public function __construct(private Engagement $engagement, private Repurposer $repurposer, private AgentPlanner $planner) {}

    // ------------------------------------------------------------ events

    /** Queue a run for each active workflow this event matches. Returns how many. */
    public static function dispatch(string $event, array $ctx, ?string $ref = null): int
    {
        $n = 0;
        foreach (Workflow::where('active', true)->get() as $wf) {
            $trigger = $wf->trigger();
            $def = Catalog::get($trigger['type'] ?? '');
            if (! $def || ($def['event'] ?? null) !== $event || ! self::matches($trigger, $ctx)) {
                continue;
            }
            if ($wf->runs()->where('created_at', '>=', now()->subHour())->where('dry_run', false)->count() >= self::MAX_RUNS_PER_HOUR) {
                continue;
            }
            try {
                $wf->runs()->create(['status' => 'queued', 'trigger_ref' => $ref, 'context' => $ctx, 'cursor' => 1, 'log' => [[
                    'key' => $trigger['key'], 'type' => $trigger['type'], 'ok' => true, 'message' => self::describeTrigger($ctx), 'at' => now()->toIso8601String(),
                ]]]);
                $n++;
            } catch (QueryException) {
                // already ran for this trigger
            }
        }
        if ($n && ! self::$drainScheduled && ! app()->runningInConsole()) {
            // In a web request, run them right after the response is sent.
            self::$drainScheduled = true;
            app()->terminating(function () {
                self::$drainScheduled = false;
                app(self::class)->drain();
            });
        }

        return $n;
    }

    public static function matches(array $trigger, array $ctx): bool
    {
        $c = $trigger['config'] ?? [];
        $platforms = array_filter((array) ($c['platforms'] ?? []));
        if ($platforms && ! in_array($ctx['platform'] ?? null, $platforms, true)) {
            return false;
        }

        return match ($trigger['type']) {
            'comment_keyword' => self::hasKeyword((string) ($ctx['text'] ?? ''), (array) ($c['keywords'] ?? [])),
            'new_dm' => empty(array_filter((array) ($c['keywords'] ?? []))) || self::hasKeyword((string) ($ctx['text'] ?? ''), (array) $c['keywords']),
            'post_performs' => (float) ($ctx['ratio'] ?? 0) >= (float) ($c['multiplier'] ?? 2),
            'any_comment' => ($ctx['intent'] ?? '') !== 'spam',
            default => true,
        };
    }

    public static function hasKeyword(string $text, array $keywords): bool
    {
        $rule = new EngagementRule(['platforms' => ['x'], 'trigger' => 'keyword', 'keywords' => $keywords, 'active' => true]);

        return $rule->matches(new EngagementItem(['platform' => 'x', 'kind' => 'comment', 'text' => $text]));
    }

    /** Context for a comment or DM. */
    public static function itemContext(EngagementItem $item): array
    {
        $post = $item->post;

        return ['item_id' => $item->id, 'kind' => $item->kind, 'platform' => $item->platform, 'text' => $item->text, 'author' => $item->author,
            'intent' => $item->intent, 'post_id' => $post?->id, 'post_title' => $post?->title, 'post_url' => $post?->url,
            'post_text' => $post ? mb_substr($post->asText(), 0, 2000) : null];
    }

    /** Context for a post (published, taking off). */
    public static function postContext(SocialPost $post, array $extra = []): array
    {
        return $extra + ['post_id' => $post->id, 'platform' => $post->platform, 'post_title' => $post->title, 'post_url' => $post->url,
            'post_text' => mb_substr($post->asText(), 0, 2000), 'text' => mb_substr($post->asText(), 0, 500), 'rate' => $post->engagement['rate'] ?? null];
    }

    /** After a sync: fire "Post takes off" for recent posts well above her usual engagement on that network. */
    public static function checkPerformance(string $platform): int
    {
        if (! Workflow::where('active', true)->get()->contains(fn ($w) => ($w->trigger()['type'] ?? '') === 'post_performs')) {
            return 0;
        }
        $posts = SocialPost::where('platform', $platform)->where('status', 'posted')->whereNotNull('posted_at')
            ->where('posted_at', '>=', now()->subDays(60))->get()->filter(fn ($p) => $p->engagement !== null)->values();
        $score = fn ($p) => $p->engagement['rate'] ?? null;
        $rates = $posts->map($score)->filter(fn ($r) => $r !== null && $r > 0)->sort()->values();
        if ($rates->count() < 5) {
            return 0; // not enough history to know what "usual" is
        }
        $median = $rates->count() % 2 ? $rates[intdiv($rates->count(), 2)] : ($rates[$rates->count() / 2 - 1] + $rates[$rates->count() / 2]) / 2;
        $n = 0;
        foreach ($posts->filter(fn ($p) => $p->posted_at->gte(now()->subDays(7))) as $p) {
            $rate = $score($p);
            if ($rate !== null && $median > 0 && $rate / $median >= 1.2) {
                $n += self::dispatch('post_performs', self::postContext($p, ['ratio' => round($rate / $median, 2), 'usual_rate' => $median]), "perf:{$p->id}");
            }
        }

        return $n;
    }

    /** Fire schedule triggers that are due today (once a day each). */
    public static function tick(?CarbonImmutable $now = null): int
    {
        $tz = SocialAnalytics::tz();
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);
        $n = 0;
        foreach (Workflow::where('active', true)->get() as $wf) {
            $t = $wf->trigger();
            if (($t['type'] ?? '') !== 'schedule') {
                continue;
            }
            $c = $t['config'] ?? [];
            $every = $c['every'] ?? 'week';
            $dueDay = match ($every) {
                'day' => true,
                'weekday' => $now->dayOfWeekIso <= 5,
                default => $now->dayOfWeekIso === (int) ($c['weekday'] ?? 1),
            };
            [$hh, $mm] = array_map('intval', explode(':', ($c['time'] ?? '09:00').':0'));
            if ($dueDay && $now->gte($now->setTime($hh, $mm)) && $now->lt($now->setTime($hh, $mm)->addHours(6))) {
                $n += self::dispatchOne($wf, ['platform' => null, 'text' => '', 'scheduled_for' => $now->toIso8601String()], 'sched:'.$now->toDateString());
            }
        }

        return $n;
    }

    private static function dispatchOne(Workflow $wf, array $ctx, ?string $ref, bool $dry = false): int
    {
        try {
            $wf->runs()->create(['status' => 'queued', 'trigger_ref' => $ref, 'context' => $ctx, 'cursor' => 1, 'dry_run' => $dry, 'log' => [[
                'key' => $wf->trigger()['key'], 'type' => $wf->trigger()['type'], 'ok' => true, 'message' => self::describeTrigger($ctx), 'at' => now()->toIso8601String(),
            ]]]);

            return 1;
        } catch (QueryException) {
            return 0;
        }
    }

    // ------------------------------------------------------------ running

    /** Run everything queued, and waiting runs whose time has come. */
    public function drain(int $limit = 50): int
    {
        $done = 0;
        $runs = WorkflowRun::where(fn ($q) => $q->where('status', 'queued')->orWhere(fn ($q) => $q->where('status', 'waiting')->where('resume_at', '<=', now())))
            ->orderBy('id')->limit($limit)->get();
        foreach ($runs as $run) {
            // Claim it, so two schedulers never run the same run.
            if (WorkflowRun::where('id', $run->id)->whereIn('status', ['queued', 'waiting'])->update(['status' => 'running']) === 0) {
                continue;
            }
            $this->execute($run->fresh());
            $done++;
        }

        return $done;
    }

    /** Manual "Run" (trigger "Run by hand") or a dry "Test" with sample data. */
    public function start(Workflow $wf, bool $dry, ?array $ctx = null): WorkflowRun
    {
        $ctx ??= $this->sampleContext($wf);
        $run = $wf->runs()->create(['status' => 'running', 'trigger_ref' => null, 'context' => $ctx, 'cursor' => 1, 'dry_run' => $dry,
            'log' => [['key' => $wf->trigger()['key'], 'type' => $wf->trigger()['type'], 'ok' => true,
                'message' => ($dry ? 'Test with sample data: ' : 'Started by you. ').self::describeTrigger($ctx), 'at' => now()->toIso8601String()]]]);
        $this->execute($run, $dry);

        return $run->fresh();
    }

    /** Test one step against sample data (nothing is sent). */
    public function testStep(Workflow $wf, string $key): array
    {
        $node = collect($wf->nodes)->firstWhere('key', $key) ?? throw new RuntimeException('Step not found.');
        if (Catalog::get($node['type'])['kind'] === 'trigger') {
            $ctx = $this->sampleContext($wf);

            return ['ok' => true, 'message' => 'Sample: '.self::describeTrigger($ctx), 'context' => $ctx];
        }
        $ctx = $this->sampleContext($wf);
        $r = $this->step($node, $ctx, true, $wf);

        return ['ok' => $r['ok'], 'message' => $r['message'], 'context' => $ctx];
    }

    private function execute(WorkflowRun $run, ?bool $dry = null): void
    {
        $dry ??= $run->dry_run;
        $wf = $run->workflow;
        $nodes = $wf->nodes;
        $ctx = $run->context ?? [];
        $log = $run->log ?? [];
        $status = 'done';
        $error = null;
        $resume = null;
        $i = max(1, $run->cursor);

        for (; $i < count($nodes); $i++) {
            $node = $nodes[$i];
            if (! ($node['enabled'] ?? true)) {
                $log[] = ['key' => $node['key'], 'type' => $node['type'], 'ok' => true, 'skipped' => true, 'message' => 'Skipped (disabled).', 'at' => now()->toIso8601String()];

                continue;
            }
            try {
                $r = $this->step($node, $ctx, $dry, $wf);
            } catch (Throwable $e) {
                $r = ['ok' => false, 'message' => $e->getMessage()];
            }
            $ctx = array_merge($ctx, $r['ctx'] ?? []);
            $log[] = ['key' => $node['key'], 'type' => $node['type'], 'ok' => $r['ok'], 'message' => $r['message'], 'at' => now()->toIso8601String()];
            if (! $r['ok']) {
                [$status, $error] = ['failed', $r['message']];
                break;
            }
            if (! empty($r['stop'])) {
                $status = 'stopped';
                break;
            }
            if (! empty($r['wait'])) {
                if ($dry) {
                    continue; // tests don't wait
                }
                [$status, $resume] = ['waiting', now()->addSeconds($r['wait'])];
                $i++;
                break;
            }
        }

        $firstPass = $run->cursor <= 1;
        $run->update(['status' => $status, 'context' => $ctx, 'log' => $log, 'cursor' => $i, 'resume_at' => $resume, 'error' => $error]);
        if (! $dry) {
            // Counted once per run, on its first pass (not again when a "Wait" resumes it).
            $wf->forceFill(['last_run_at' => now(), 'runs_count' => $wf->runs_count + ($firstPass ? 1 : 0)])->save();
        }
        if ($status === 'failed' && ! $dry) {
            AgentEvent::log('failed', "Workflow “{$wf->name}” stopped at a step: {$error}");
        }
    }

    /** One step. Returns ok, message, and optionally ctx, stop, wait (seconds). */
    private function step(array $node, array $ctx, bool $dry, Workflow $wf): array
    {
        $c = $node['config'] ?? [];
        $def = Catalog::get($node['type']) ?? throw new RuntimeException("Unknown step {$node['type']}.");
        if (! Catalog::configured($node)) {
            return ['ok' => false, 'message' => "“{$def['label']}” isn’t set up yet."];
        }
        $item = ($def['needs'] ?? null) === 'item' ? $this->item($ctx, $dry) : null;
        $post = ($def['needs'] ?? null) === 'post' ? $this->post($ctx) : null;

        switch ($node['type']) {
            case 'condition':
                $pass = $this->compare($c, $ctx);

                return ['ok' => true, 'stop' => ! $pass, 'message' => $pass ? 'Condition met, continuing.' : 'Condition not met, stopped here.'];

            case 'delay':
                $secs = (int) $c['amount'] * ['minutes' => 60, 'hours' => 3600, 'days' => 86400][$c['unit'] ?? 'minutes'];

                return ['ok' => true, 'wait' => $secs, 'message' => $dry ? "Would wait {$c['amount']} {$c['unit']} (skipped in a test)." : "Waiting {$c['amount']} {$c['unit']}."];

            case 'reply_comment':
                if (($ctx['kind'] ?? 'comment') === 'dm') {
                    return ['ok' => false, 'message' => 'This is a DM, not a comment: use “Send DM”.'];
                }
                $text = ($c['mode'] ?? 'ai') === 'template' ? $this->fill($c['text'], $ctx) : $this->engagement->draft($item, $c['instructions'] ?? null);
                if ($dry) {
                    return ['ok' => true, 'message' => "Would reply: {$text}"];
                }
                $this->engagement->send($item, $text, null, 'workflow');

                return ['ok' => true, 'message' => "Replied: {$text}", 'ctx' => ['replied' => true]];

            case 'send_dm':
                if (($ctx['platform'] ?? null) !== 'instagram') {
                    return ['ok' => false, 'message' => (Platforms::PLATFORMS[$ctx['platform'] ?? '']['label'] ?? 'This network').' doesn’t allow DMs from the studio; only Instagram does.'];
                }
                $text = ($c['mode'] ?? 'template') === 'template' ? $this->fill($c['text'], $ctx)
                    : $this->engagement->draft($item, trim(($c['instructions'] ?? '').' This is a private DM.'));
                if ($dry) {
                    return ['ok' => true, 'message' => "Would DM @{$ctx['author']}: {$text}"];
                }
                $item->kind === 'dm' ? $this->engagement->send($item, $text, null, 'workflow') : $this->engagement->send($item, null, $text, 'workflow');

                return ['ok' => true, 'message' => "DM sent: {$text}", 'ctx' => ['replied' => true]];

            case 'ai_post':
            case 'repurpose':
                $platforms = array_values(array_intersect((array) $c['platforms'], array_keys(Platforms::PLATFORMS)));
                if ($node['type'] === 'repurpose') {
                    $platforms = array_values(array_diff($platforms, [$post->platform]));
                    if (! $platforms) {
                        return ['ok' => true, 'message' => 'Nothing to do: the post is already on those networks.'];
                    }
                }
                $labels = implode(', ', array_map(fn ($p) => Platforms::PLATFORMS[$p]['label'], $platforms));
                if ($dry) {
                    return ['ok' => true, 'message' => "Would write ".count($platforms)." post(s) for {$labels}".(($c['status'] ?? 'draft') === 'scheduled' ? ' and schedule them.' : ' as drafts.')];
                }
                $targets = array_map(fn ($p) => ['platform' => $p, 'format' => AgentPlanner::FORMATS[$p]], $platforms);
                $made = $node['type'] === 'repurpose'
                    ? $this->repurposer->run($post->title ?: 'A post that did well', $post->asText(), $targets, $post->script_id, 'This post performed well on '.Platforms::PLATFORMS[$post->platform]['label'].'; keep what made it work.', 20)
                    : $this->repurposer->run($wf->name, $this->fill($c['brief'], $ctx), $targets, null, '', 20);
                $taken = collect();
                foreach ($made as $p) {
                    $attrs = ['source' => 'workflow', 'source_post_id' => $node['type'] === 'repurpose' ? $post->id : null];
                    if (($c['status'] ?? 'draft') === 'scheduled') {
                        $attrs += ['status' => 'scheduled', 'approval' => 'approved', 'scheduled_for' => SocialScheduler::nextSlot($p->platform, null, $p->id, $taken)['at']];
                    }
                    $p->update($attrs);
                    $taken->push($p);
                }

                return ['ok' => true, 'message' => 'Wrote '.count($made)." post(s) for {$labels}".(($c['status'] ?? 'draft') === 'scheduled' ? ', scheduled.' : ', saved as drafts.'),
                    'ctx' => ['created_post_ids' => array_map(fn ($p) => $p->id, $made)]];

            case 'run_agent':
                $agent = SocialAgent::find($c['agent_id']) ?? throw new RuntimeException('That agent no longer exists.');
                if ($dry) {
                    return ['ok' => true, 'message' => "Would let “{$agent->name}” plan the next 7 days (".array_sum($this->planner->needed($agent)).' post(s) to write).'];
                }
                AgentPlanner::assertReady();

                return ['ok' => true, 'message' => "“{$agent->name}” planned ".count($this->planner->run($agent)).' post(s).'];

            case 'notify':
                $msg = $this->fill($c['message'], $ctx);
                if (! $dry) {
                    AgentEvent::log('workflow', "{$wf->name}: {$msg}", null, $ctx['post_id'] ?? null, $ctx['platform'] ?? null);
                }

                return ['ok' => true, 'message' => ($dry ? 'Would notify you: ' : 'Notified you: ').$msg];

            case 'mark':
                if ($dry) {
                    return ['ok' => true, 'message' => "Would mark it {$c['as']}."];
                }
                $item->update(['status' => $c['as'] === 'replied' ? 'replied' : 'ignored', 'replied_by' => $c['as'] === 'replied' ? 'workflow' : $item->replied_by]);

                return ['ok' => true, 'message' => "Marked {$c['as']}."];
        }

        return ['ok' => false, 'message' => "Unknown step {$node['type']}."];
    }

    private function item(array $ctx, bool $dry): EngagementItem
    {
        $item = isset($ctx['item_id']) ? EngagementItem::find($ctx['item_id']) : null;
        if ($item) {
            return $item;
        }
        if ($dry) {
            // Sample data in a test: an unsaved item, so AI drafts still work.
            return new EngagementItem(['platform' => $ctx['platform'] ?: 'instagram', 'kind' => $ctx['kind'] ?? 'comment', 'text' => $ctx['text'] ?: 'Love this! Where can I find the guide?',
                'author' => $ctx['author'] ?? 'sample_user', 'intent' => $ctx['intent'] ?? 'question']);
        }

        throw new RuntimeException('This step needs a comment or DM, and this trigger doesn’t give one.');
    }

    private function post(array $ctx): SocialPost
    {
        return (isset($ctx['post_id']) ? SocialPost::find($ctx['post_id']) : null)
            ?? throw new RuntimeException('This step needs a post, and this trigger doesn’t give one.');
    }

    private function compare(array $c, array $ctx): bool
    {
        $actual = match ($c['field']) {
            'rate' => $ctx['rate'] ?? null,
            'followers_ratio' => $ctx['ratio'] ?? null,
            default => $ctx[$c['field']] ?? null,
        };
        $a = mb_strtolower(trim((string) $actual));
        $v = mb_strtolower(trim((string) $c['value']));

        return match ($c['op']) {
            'contains' => $v !== '' && str_contains($a, $v),
            'not_contains' => $v === '' || ! str_contains($a, $v),
            'is' => $a === $v,
            'is_not' => $a !== $v,
            'gt' => is_numeric($actual) && (float) $actual > (float) $v,
            'lt' => is_numeric($actual) && (float) $actual < (float) $v,
            default => false,
        };
    }

    /** {text}, {author}, {name} (= @author), {platform}, {post_title}, {post_url}, {post_text}. */
    public function fill(string $template, array $ctx): string
    {
        $vars = [
            '{text}' => $ctx['text'] ?? '', '{author}' => $ctx['author'] ?? '', '{name}' => ! empty($ctx['author']) ? '@'.ltrim($ctx['author'], '@') : '',
            '{platform}' => isset($ctx['platform']) ? (Platforms::PLATFORMS[$ctx['platform']]['label'] ?? $ctx['platform']) : '',
            '{post_title}' => $ctx['post_title'] ?? '', '{post_url}' => $ctx['post_url'] ?? '', '{post_text}' => $ctx['post_text'] ?? '',
        ];

        return trim(preg_replace('/ {2,}/', ' ', strtr($template, $vars)));
    }

    /** Realistic sample data for a test, taken from her own inbox/posts when there is some. */
    public function sampleContext(Workflow $wf): array
    {
        $t = $wf->trigger();
        $c = $t['config'] ?? [];
        $platforms = array_filter((array) ($c['platforms'] ?? []));
        switch (Catalog::get($t['type'])['event'] ?? 'manual') {
            case 'comment':
            case 'dm':
                $kind = $t['type'] === 'new_dm' ? 'dm' : 'comment';
                $item = EngagementItem::where('kind', $kind)->when($platforms, fn ($q) => $q->whereIn('platform', $platforms))->latest('received_at')->first();
                if ($item) {
                    return self::itemContext($item) + ['sample' => true];
                }
                $kw = (array) ($c['keywords'] ?? []);

                return ['kind' => $kind, 'platform' => $platforms[0] ?? 'instagram', 'author' => 'sample_user', 'intent' => 'question', 'sample' => true,
                    'text' => $kw ? 'Can I get the '.$kw[0].' please?' : ($kind === 'dm' ? 'Hi! Do you do collabs with tech brands?' : 'Love this! Which mic do you use?')];
            case 'post_published':
            case 'post_performs':
                $post = SocialPost::where('status', 'posted')->when($platforms, fn ($q) => $q->whereIn('platform', $platforms))->latest('posted_at')->first();

                return $post ? self::postContext($post, ['ratio' => (float) ($c['multiplier'] ?? 2), 'sample' => true])
                    : ['platform' => $platforms[0] ?? 'linkedin', 'text' => 'Sample post', 'post_text' => 'Sample post', 'ratio' => 2, 'sample' => true];
            default:
                return ['platform' => null, 'text' => '', 'sample' => true];
        }
    }

    public static function describeTrigger(array $ctx): string
    {
        $net = isset($ctx['platform']) && $ctx['platform'] ? (Platforms::PLATFORMS[$ctx['platform']]['label'] ?? $ctx['platform']) : null;
        if (isset($ctx['kind'])) {
            return ($ctx['kind'] === 'dm' ? 'DM' : 'Comment').(! empty($ctx['author']) ? " from @{$ctx['author']}" : '').($net ? " on {$net}" : '').': '.mb_substr((string) $ctx['text'], 0, 140);
        }
        if (isset($ctx['ratio']) && isset($ctx['post_text'])) {
            return "{$net} post at {$ctx['ratio']}× usual engagement: ".mb_substr((string) $ctx['post_text'], 0, 100);
        }
        if (isset($ctx['post_text'])) {
            return "{$net} post published: ".mb_substr((string) $ctx['post_text'], 0, 100);
        }

        return isset($ctx['scheduled_for']) ? 'Scheduled run.' : 'Started.';
    }

    /** Validate and normalise a node list from the builder. */
    public static function normalise(array $nodes): array
    {
        $out = [];
        $keys = [];
        foreach (array_values($nodes) as $i => $n) {
            $def = Catalog::get((string) ($n['type'] ?? ''));
            if (! $def) {
                throw new RuntimeException('Unknown step type.');
            }
            if (($i === 0) !== ($def['kind'] === 'trigger')) {
                throw new RuntimeException($i === 0 ? 'A workflow starts with a trigger.' : 'Only the first step can be a trigger.');
            }
            // Keys the builder didn't set follow its own naming: trigger_1, action_1, logic_1, …
            $key = preg_replace('/[^a-z0-9_]/', '', (string) ($n['key'] ?? ''));
            for ($k = 1; $key === '' || isset($keys[$key]); $k++) {
                $key = $def['kind'].'_'.$k;
            }
            $keys[$key] = true;
            $allowed = array_column($def['fields'], 'key');
            $out[] = ['key' => $key, 'type' => $n['type'], 'config' => array_intersect_key((array) ($n['config'] ?? []), array_flip($allowed)), 'enabled' => (bool) ($n['enabled'] ?? true)];
        }
        if (! $out) {
            throw new RuntimeException('A workflow starts with a trigger.');
        }
        if (count($out) > 40) {
            throw new RuntimeException('That’s a lot of steps: 40 at most.');
        }

        return $out;
    }

    /** Problems that block turning a workflow on (empty = ready). */
    public static function problems(array $nodes): array
    {
        $p = [];
        if (count($nodes) < 2) {
            $p[] = 'Add at least one step after the trigger.';
        }
        foreach ($nodes as $n) {
            if (($n['enabled'] ?? true) && ! Catalog::configured($n)) {
                $p[] = '“'.(Catalog::get($n['type'])['label'] ?? $n['type']).'” isn’t set up yet.';
            }
        }
        $event = Catalog::get($nodes[0]['type'] ?? '')['event'] ?? null;
        foreach (array_slice($nodes, 1) as $n) {
            $needs = Catalog::get($n['type'])['needs'] ?? null;
            if ($needs === 'item' && ! in_array($event, ['comment', 'dm'], true)) {
                $p[] = '“'.Catalog::get($n['type'])['label'].'” needs a comment or DM trigger.';
            }
            if ($needs === 'post' && ! in_array($event, ['post_published', 'post_performs'], true)) {
                $p[] = '“'.Catalog::get($n['type'])['label'].'” needs a post trigger (Post published or Post takes off).';
            }
        }

        return array_values(array_unique($p));
    }
}
