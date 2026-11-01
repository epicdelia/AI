<?php

namespace App\Domains\Agents\Harness;

use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use App\Services\Llm;
use App\Services\Prompts;

/**
 * Autonomous Weekly Production Sprint & Operations Director.
 * Synthesizes active brand deal commitments, video pipeline status, and social calendar slots
 * into an optimized, high-leverage weekly production plan with smart batching recommendations.
 */
class SprintOrchestrator
{
    public function __construct(
        private Llm $llm
    ) {
    }

    /**
     * Plan the creator's weekly production sprint.
     *
     * @param  array{week_start?: string, hours_available?: int, focus_topic?: string}  $params
     * @return array{
     *     sprint_dates: string,
     *     target_commitments: array{
     *         youtube_videos: int,
     *         social_posts: int,
     *         sponsor_deliverables: int
     *     },
     *     batching_strategy: array<int, array{
     *         session_name: string,
     *         duration_hours: float,
     *         tasks_combined: array<int, string>,
     *         efficiency_multiplier: string,
     *         day: string
     *     }>,
     *     daily_schedule: array<string, array<int, string>>,
     *     sponsor_deadline_alerts: array<int, array{
     *         brand: string,
     *         deliverable: string,
     *         status: string,
     *         urgency: string
     *     }>,
     *     operations_advice: string
     * }
     */
    public function planSprint(SocialAgent $agent, array $params = []): array
    {
        $hoursAvailable = (int) ($params['hours_available'] ?? 15);
        $focusTopic = (string) ($params['focus_topic'] ?? '');

        // 1. Check active deals and upcoming deliverables
        $activeDeals = Deal::whereNotIn('stage', ['paid', 'lost', 'prospect'])
            ->get(['brand_name', 'stage', 'deliverables', 'rate', 'coupon_code']);

        // 2. Check pending scripts
        $pendingScripts = Script::whereIn('status', ['draft', 'reviewed', 'writing'])
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'status']);

        // 3. Check scheduled posts for next 7 days
        $scheduledCount = SocialPost::where('status', 'scheduled')
            ->where('scheduled_for', '>=', now())
            ->where('scheduled_for', '<=', now()->addDays(7))
            ->count();

        $creatorName = Prompts::name();

        $system = <<<PROMPT
You are the Executive Producer and Operations Director for {$creatorName} (@Tech.Unicorn).
Your mission is to organize {$creatorName}'s upcoming week into an elite, frictionless production sprint that:
1. Guarantees 100% on-time fulfillment of active sponsor deliverables.
2. Maintains the YouTube long-form weekly shipping streak without creator burnout.
3. Groups filming and writing into high-leverage "batching blocks" (e.g. batch-filming 1 main video + 3 short-form hooks in one session).
4. Provides a day-by-day roadmap with actionable daily priorities.

Return ONLY a valid JSON object matching this schema:
{
  "sprint_dates": "Nov 1 - Nov 7, 2026",
  "target_commitments": {
    "youtube_videos": 1,
    "social_posts": 6,
    "sponsor_deliverables": 2
  },
  "batching_strategy": [
    {
      "session_name": "Studio Filming & B-Roll Batch",
      "duration_hours": 2.5,
      "tasks_combined": ["Main YouTube Video A-Roll", "Sponsor Midroll Demo", "3x TikTok/Shorts Hook Variants"],
      "efficiency_multiplier": "2.8x faster than disjointed filming",
      "day": "Tuesday"
    }
  ],
  "daily_schedule": {
    "Monday": ["Finalize script outline and sponsor talking points", "Batch-schedule 3 LinkedIn posts"],
    "Tuesday": ["Record A-Roll and 3 Short hooks", "Generate sponsor cut for brand pre-approval"]
  },
  "sponsor_deadline_alerts": [
    {
      "brand": "DataFlow AI",
      "deliverable": "60s YouTube midroll",
      "status": "In Production",
      "urgency": "High"
    }
  ],
  "operations_advice": "Lock in the sponsor segment before Tuesday noon to allow 24h brand approval window."
}
PROMPT;

        $dealsSummary = $activeDeals->map(fn($d) => "- {$d->brand_name} ({$d->stage}): " . (is_array($d->deliverables) ? implode(', ', $d->deliverables) : $d->deliverables))->implode("\n");
        $scriptsSummary = $pendingScripts->map(fn($s) => "- {$s->title} [Status: {$s->status}]")->implode("\n");

        $prompt = <<<TEXT
Generate Weekly Production Sprint for:
HOURS AVAILABLE: {$hoursAvailable}
FOCUS TOPIC: {$focusTopic}

ACTIVE SPONSOR CONTRACTS:
{$dealsSummary}

PIPELINE SCRIPTS:
{$scriptsSummary}

SCHEDULED SOCIAL POSTS NEXT 7 DAYS: {$scheduledCount}
POSTS PER WEEK TARGET: {$agent->posts_per_week}
PLATFORMS: {$agent->formatted_platforms}
TEXT;

        try {
            $data = $this->llm->as('sprint-orchestrator')->creativity(0.3)->json($system, $prompt);
        } catch (\Throwable) {
            $data = null;
        }

        if (!is_array($data) || !isset($data['daily_schedule'])) {
            return [
                'sprint_dates' => now()->format('M j') . ' - ' . now()->addDays(6)->format('M j, Y'),
                'target_commitments' => [
                    'youtube_videos' => 1,
                    'social_posts' => max(4, $agent->posts_per_week),
                    'sponsor_deliverables' => $activeDeals->count(),
                ],
                'batching_strategy' => [
                    [
                        'session_name' => 'Deep Writing & Research Sprint',
                        'duration_hours' => 2.0,
                        'tasks_combined' => ['YouTube script final draft', 'Transmedia cascade to 5 platforms'],
                        'efficiency_multiplier' => '2.5x leverage',
                        'day' => 'Monday',
                    ],
                    [
                        'session_name' => 'Studio Production Block',
                        'duration_hours' => 2.5,
                        'tasks_combined' => ['Record main video', 'Record sponsor integration', 'Record 3 Shorts hooks'],
                        'efficiency_multiplier' => '3x efficiency',
                        'day' => 'Tuesday',
                    ],
                ],
                'daily_schedule' => [
                    'Monday' => ['Finalize video script & audit retention pacing', 'Review active sponsor talking points'],
                    'Tuesday' => ['Batch-film main video + vertical hooks', 'Submit sponsor cut for brand preview'],
                    'Wednesday' => ['Transmedia cascade: schedule LinkedIn & X threads', 'Triage community leads in inbox'],
                    'Thursday' => ['Final cut review & package YouTube metadata', 'Audit LinkedIn draft with Algorithm Shield'],
                    'Friday' => ['Publish YouTube video & monitor initial 3h velocity', 'Engage in comments to drive browse push'],
                    'Saturday' => ['Light review of weekly analytics and sponsor clicks'],
                    'Sunday' => ['Run Weekly Retrospective & calibrate Agent Learner lessons'],
                ],
                'sponsor_deadline_alerts' => $activeDeals->map(fn($d) => [
                    'brand' => $d->brand_name,
                    'deliverable' => is_array($d->deliverables) ? implode(', ', $d->deliverables) : (string) $d->deliverables,
                    'status' => $d->stage,
                    'urgency' => in_array($d->stage, ['contracted', 'production']) ? 'High' : 'Normal',
                ])->values()->all(),
                'operations_advice' => 'Batching your video recording into a single 2.5-hour block protects deep technical coding time during the rest of the week.',
            ];
        }

        return $data;
    }
}