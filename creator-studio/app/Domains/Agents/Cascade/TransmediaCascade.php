<?php

namespace App\Domains\Agents\Cascade;

use App\Domains\Agents\Guardrails\AgentGuardrails;
use App\Domains\Agents\Memory\AgentEpisodicMemory;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Domains\Social\PostScorer;
use App\Domains\Social\SocialScheduler;
use App\Models\Script;
use App\Services\AiTellsLinter;
use App\Services\Llm;
use App\Services\Prompts;
use Throwable;

/**
 * Omnichannel Transmedia Cascade Engine.
 * Multiplies a single core video script, technical topic, or deep-dive concept into
 * a synchronized cross-platform campaign across LinkedIn, X Threads, Short-Form Video (TikTok/Reels/Shorts),
 * Newsletter/Substack Deep-Dives, and Multi-Slide Carousels.
 */
class TransmediaCascade
{
    public function __construct(
        private Llm $llm,
        private AgentGuardrails $guardrails,
        private AgentEpisodicMemory $memory,
        private PostScorer $scorer
    ) {
    }

    /**
     * Generate a full transmedia cascade across 5 distinct media formats.
     *
     * @param  array{topic?: string, script_id?: int, notes?: string}  $params
     * @return array{
     *     cascade: array{
     *         linkedin: array{platform: string, title: string, body: string, parts: array, score: int, ai_tells_count: int},
     *         x_thread: array{platform: string, title: string, hook_tweet: string, thread_tweets: array<int, string>, score: int, ai_tells_count: int},
     *         short_form: array{platform: string, title: string, hook_3s: string, script_body: string, on_screen_cues: array<int, string>, duration_seconds: int},
     *         newsletter: array{title: string, executive_summary: string, deep_dive: string, action_item: string},
     *         carousel: array{title: string, slides: array<int, array{slide_number: int, headline: string, body: string, visual_cue: string}>}
     *     },
     *     source_summary: string,
     *     total_assets: int
     * }
     */
    public function generate(SocialAgent $agent, array $params): array
    {
        $topic = trim((string)($params['topic'] ?? ''));
        $script = null;

        if (!empty($params['script_id'])) {
            $script = Script::find($params['script_id']);
            if ($script && empty($topic)) {
                $topic = $script->title;
            }
        }

        if (empty($topic)) {
            $topic = 'Modern distributed systems and event-driven architecture';
        }

        $sourceContext = '';
        if ($script) {
            $sourceContext = "Script Outline & Notes:\n" . mb_substr((string)($script->outline ?: $script->notes), 0, 3000) . "\n\n";
        }

        $creatorName = Prompts::name();
        $brandTone = $agent->brand ?: 'Pragmatic, high-signal software engineering with zero fluff.';

        $systemPrompt = <<<SYS
You are the Transmedia Publishing Architect for {$creatorName} (@Tech.Unicorn).
Brand identity: {$brandTone}

MISSION:
Transform the given core subject into a synchronized, 5-asset distribution cascade.
Every asset must be tailored to its specific platform algorithms and audience consumption habits.

REQUIRED ASSETS:
1. linkedin:
   - Formatted technical thought-leadership post.
   - Compelling 1-line practitioner hook (no clichés).
   - Problem narrative + technical architectural solution + 3 concrete takeaways + engaging closing question.
2. x_thread:
   - High-velocity X / Twitter thread.
   - Hook tweet (<280 chars) that sparks fierce curiosity or challenges common consensus.
   - 4 to 6 sequential insight tweets that break down the concept step-by-step with code/mental model pointers.
   - Final CTA tweet.
3. short_form:
   - 45-60s spoken vertical video script for TikTok/Instagram Reels/YouTube Shorts.
   - 0-3s pattern-interrupt hook (visual action + spoken line).
   - Verbatim spoken dialogue with explicit [ON-SCREEN TEXT: ...] and [VISUAL: ...] cues.
4. newsletter:
   - Newsletter / Substack deep-dive edition.
   - Executive summary, in-depth architectural breakdown, and "Experiment to Try This Week".
5. carousel:
   - 7-slide visual carousel outline.
   - Slide 1: Bold cover hook, Slides 2-6: Step-by-step mental models, Slide 7: Summary & bookmark CTA.

Return a valid JSON object matching this exact schema:
{
  "linkedin": {
    "title": "Short title",
    "body": "Full LinkedIn post text",
    "parts": []
  },
  "x_thread": {
    "title": "Thread title",
    "hook_tweet": "Tweet 1 hook text",
    "thread_tweets": ["Tweet 2...", "Tweet 3...", "Tweet 4...", "Tweet 5...", "Tweet 6 CTA..."]
  },
  "short_form": {
    "title": "Short-form video title",
    "hook_3s": "Spoken hook with visual action",
    "script_body": "Verbatim spoken script with [TEXT] and [VISUAL] markers",
    "on_screen_cues": ["Cue 1", "Cue 2", "Cue 3"],
    "duration_seconds": 55
  },
  "newsletter": {
    "title": "Newsletter Subject Line",
    "executive_summary": "2-3 sentence executive takeaway",
    "deep_dive": "Markdown deep-dive body with headers and code pointers",
    "action_item": "Concrete actionable challenge for engineers this week"
  },
  "carousel": {
    "title": "Carousel Title",
    "slides": [
      {"slide_number": 1, "headline": "Slide 1 Hook", "body": "Bullets", "visual_cue": "Diagram description"},
      {"slide_number": 2, "headline": "The Common Anti-Pattern", "body": "Bullets", "visual_cue": "Illustration"},
      {"slide_number": 3, "headline": "The Architectural Shift", "body": "Bullets", "visual_cue": "Diagram"},
      {"slide_number": 4, "headline": "Real-World Benchmark", "body": "Bullets", "visual_cue": "Chart"},
      {"slide_number": 5, "headline": "Edge Cases & Gotchas", "body": "Bullets", "visual_cue": "Warning badge"},
      {"slide_number": 6, "headline": "Implementation Checklist", "body": "Bullets", "visual_cue": "Checklist graphic"},
      {"slide_number": 7, "headline": "Key Takeaway", "body": "Summary & Save this post", "visual_cue": "Bookmark icon"}
    ]
  }
}
SYS;

        $userPrompt = "Core Subject: {$topic}\n\n{$sourceContext}"
            . (!empty($params['notes']) ? "Additional Notes: {$params['notes']}\n" : '');

        $this->guardrails->recordTokens((int)(mb_strlen($systemPrompt . $userPrompt) / 4), 900);

        try {
            $raw = $this->llm->as('transmedia-cascade')->creativity(0.6)->json($systemPrompt, $userPrompt);

            if (!isset($raw['linkedin'], $raw['x_thread'], $raw['short_form'])) {
                throw new \RuntimeException('Incomplete transmedia cascade payload.');
            }
        } catch (Throwable $e) {
            $raw = $this->fallbackCascade($topic);
        }

        // Post-processing: Quality linting and scoring
        $liBody = (string)($raw['linkedin']['body'] ?? '');
        $liLints = AiTellsLinter::lint($liBody);
        $raw['linkedin']['platform'] = 'linkedin';
        $raw['linkedin']['ai_tells_count'] = count($liLints);
        $raw['linkedin']['score'] = $this->scorer->score([
            'platform' => 'linkedin',
            'format' => 'post',
            'body' => $liBody,
            'parts' => [],
        ])['score'] ?? 85;

        $xHook = (string)($raw['x_thread']['hook_tweet'] ?? '');
        $xTweets = (array)($raw['x_thread']['thread_tweets'] ?? []);
        $raw['x_thread']['platform'] = 'x';
        $raw['x_thread']['ai_tells_count'] = count(AiTellsLinter::lint($xHook));
        $raw['x_thread']['score'] = $this->scorer->score([
            'platform' => 'x',
            'format' => 'thread',
            'body' => $xHook,
            'parts' => $xTweets,
        ])['score'] ?? 88;

        $raw['short_form']['platform'] = 'tiktok';

        return [
            'cascade' => $raw,
            'source_summary' => "Transmedia Cascade generated from: “{$topic}”.",
            'total_assets' => 5,
        ];
    }

    /**
     * Batch-schedule the social assets from a generated cascade into the creator's social calendar.
     *
     * @param  array $agent{
     *     cascade: array,
     *     platforms_to_schedule?: array<int, string>
     * }  $params
     * @return array<int, SocialPost>
     */
    public function scheduleCascade(SocialAgent $agent, array $params): array
    {
        $cascade = $params['cascade'] ?? [];
        $platforms = $params['platforms_to_schedule'] ?? ['linkedin', 'x'];

        $createdPosts = [];
        $taken = SocialPost::where('status', 'scheduled')
            ->where('scheduled_for', '>=', now())
            ->get();

        $auto = $agent->approval === 'auto';

        // 1. LinkedIn Post
        if (in_array('linkedin', $platforms, true) && !empty($cascade['linkedin']['body'])) {
            $slot = SocialScheduler::nextSlot('linkedin', null, null, $taken);
            $post = SocialPost::create([
                'platform' => 'linkedin',
                'format' => 'post',
                'title' => mb_substr((string)($cascade['linkedin']['title'] ?? 'LinkedIn Thought Leadership'), 0, 200),
                'body' => trim((string)$cascade['linkedin']['body']),
                'parts' => [],
                'status' => $auto ? 'scheduled' : 'draft',
                'scheduled_for' => $slot['at'],
                'source' => 'transmedia_cascade',
                'social_agent_id' => $agent->id,
                'approval' => $auto ? 'approved' : 'pending',
                'agent_original' => trim((string)$cascade['linkedin']['body']),
                'notes' => "Part of Transmedia Cascade.\nSlot: {$slot['reason']}",
            ]);
            $taken->push($post);
            $createdPosts[] = $post;
        }

        // 2. X Thread
        if (in_array('x', $platforms, true) && !empty($cascade['x_thread']['hook_tweet'])) {
            $slot = SocialScheduler::nextSlot('x', null, null, $taken);
            $hook = trim((string)$cascade['x_thread']['hook_tweet']);
            $tweets = (array)($cascade['x_thread']['thread_tweets'] ?? []);

            $post = SocialPost::create([
                'platform' => 'x',
                'format' => 'thread',
                'title' => mb_substr((string)($cascade['x_thread']['title'] ?? 'X Viral Thread'), 0, 200),
                'body' => $hook,
                'parts' => $tweets,
                'status' => $auto ? 'scheduled' : 'draft',
                'scheduled_for' => $slot['at'],
                'source' => 'transmedia_cascade',
                'social_agent_id' => $agent->id,
                'approval' => $auto ? 'approved' : 'pending',
                'agent_original' => trim($hook . "\n\n" . implode("\n\n", $tweets)),
                'notes' => "Part of Transmedia Cascade.\nSlot: {$slot['reason']}",
            ]);
            $taken->push($post);
            $createdPosts[] = $post;
        }

        // 3. Short-Form Video draft
        if (in_array('tiktok', $platforms, true) && !empty($cascade['short_form']['script_body'])) {
            $slot = SocialScheduler::nextSlot('tiktok', null, null, $taken);
            $body = trim((string)$cascade['short_form']['script_body']);

            $post = SocialPost::create([
                'platform' => 'tiktok',
                'format' => 'video',
                'title' => mb_substr((string)($cascade['short_form']['title'] ?? 'Short-Form Video Script'), 0, 200),
                'body' => $body,
                'parts' => (array)($cascade['short_form']['on_screen_cues'] ?? []),
                'status' => 'draft', // Short-form videos always require filming review
                'scheduled_for' => $slot['at'],
                'source' => 'transmedia_cascade',
                'social_agent_id' => $agent->id,
                'approval' => 'pending',
                'agent_original' => $body,
                'notes' => "Short-form spoken script with visual cues.\nSlot: {$slot['reason']}",
            ]);
            $taken->push($post);
            $createdPosts[] = $post;
        }

        AgentEvent::log(
            'drafted',
            'Transmedia Cascade scheduled ' . count($createdPosts) . " post(s) into calendar for {$agent->name}.",
            $agent->id
        );

        return $createdPosts;
    }

    private function fallbackCascade(string $topic): array
    {
        return [
            'linkedin' => [
                'title' => "Deep Dive: {$topic}",
                'body' => "Most teams tackle {$topic} by copying whatever Netflix or Uber did five years ago.\n\nHere is why that usually backfires—and the 3 architectural rules we use instead:\n\n1. Measure throughput before optimizing latency.\n2. Keep your domain boundaries strict.\n3. Make failure states idempotent.\n\nWhat is your team's biggest challenge when tackling this?",
                'parts' => [],
            ],
            'x_thread' => [
                'title' => "The hidden truth about {$topic}",
                'hook_tweet' => "90% of engineers overcomplicate {$topic}.\n\nHere's the mental model that took me 8 years to understand (and will save you 100+ hours of debugging): 🧵👇",
                'thread_tweets' => [
                    "1/ The Root Problem:\nTeams reach for distributed patterns when single-process concurrency would easily handle their peak load.",
                    "2/ The Hidden Cost:\nNetwork partitions, clock drift, and distributed consensus overhead consume more dev hours than feature delivery.",
                    "3/ The Antidote:\nBuild modular monoliths with asynchronous event boundaries before breaking into microservices.",
                    "4/ Key Rule:\nIf you can't describe your failure recovery in 2 sentences, your architecture is already brittle.",
                    "5/ Summary:\nKeep it simple until the numbers force you to evolve. Bookmark this thread if you're architecting your next service.",
                ],
            ],
            'short_form' => [
                'title' => "Stop making this mistake with {$topic}",
                'hook_3s' => "[VISUAL: Holding up laptop with red error logs] If you're building {$topic}, please stop doing this one thing right now.",
                'script_body' => "[TEXT: THE #1 MISTAKE IN {$topic}]\nI see senior devs make this mistake every single week. They start by setting up five microservices before they even validate the core query path.\n\n[VISUAL: Screen recording of simplified database architecture]\nHere is what you should do instead. Write your transactions in an append-only event stream. That gives you auditability and zero lock contention.\n\n[TEXT: SAVE THIS FOR YOUR NEXT SPRINT]\nDrop a comment if you've been burned by this before.",
                'on_screen_cues' => ['STOP DOING THIS', 'THE RIGHT PATTERN', 'APPEND-ONLY LOGS'],
                'duration_seconds' => 50,
            ],
            'newsletter' => [
                'title' => "The Engineer's Guide to {$topic}",
                'executive_summary' => "A practical breakdown of how to design reliable systems around {$topic} without unnecessary infrastructure complexity.",
                'deep_dive' => "## The Architectural Dilemma\n\nWhen scaling systems, the default instinct is often to distribute components prematurely...",
                'action_item' => 'Audit your slowest query or pipeline this week. Identify if network hops or locks are the bottleneck.',
            ],
            'carousel' => [
                'title' => '7 Rules of Modern Architecture',
                'slides' => [
                    [
                        'slide_number' => 1,
                        'headline' => "The Architecture Blueprint: {$topic}",
                        'body' => '7 practical lessons every tech lead should know.',
                        'visual_cue' => 'High contrast title card',
                    ],
                    [
                        'slide_number' => 2,
                        'headline' => '1. The Premature Distribution Trap',
                        'body' => 'Why microservices add latency before they add scale.',
                        'visual_cue' => 'Monolith vs Microservices graphic',
                    ],
                    [
                        'slide_number' => 3,
                        'headline' => '2. Idempotency Over Locking',
                        'body' => 'How safe retries beat distributed locks every time.',
                        'visual_cue' => 'Retry queue diagram',
                    ],
                    [
                        'slide_number' => 4,
                        'headline' => '3. Observability First',
                        'body' => "If you can't trace the request across hops, you're flying blind.",
                        'visual_cue' => 'Distributed trace mockup',
                    ],
                    [
                        'slide_number' => 5,
                        'headline' => '4. Eventual Consistency Realities',
                        'body' => 'Accept that state sync takes non-zero milliseconds.',
                        'visual_cue' => 'Timeline diagram',
                    ],
                    [
                        'slide_number' => 6,
                        'headline' => '5. Failure Domain Isolation',
                        'body' => 'Keep secondary features from bringing down checkout.',
                        'visual_cue' => 'Bulkhead pattern icon',
                    ],
                    [
                        'slide_number' => 7,
                        'headline' => 'Save This Guide',
                        'body' => 'Follow @Tech.Unicorn for weekly engineering deep-dives.',
                        'visual_cue' => 'Bookmark & share icon',
                    ],
                ],
            ],
        ];
    }
}
