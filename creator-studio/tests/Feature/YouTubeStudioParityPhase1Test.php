<?php

namespace Tests\Feature;

use App\Domains\Social\Models\SocialAccount;
use App\Domains\YouTube\AbTesting\BayesianAbOptimizer;
use App\Domains\YouTube\PreFlight\AdSuitabilityShield;
use App\Domains\YouTube\PreFlight\AudioLoudnessAnalyzer;
use App\Domains\YouTube\PreFlight\PreFlightSuite;
use App\Domains\YouTube\VideoAbTest;
use App\Domains\YouTube\VideoCheck;
use App\Models\Deal;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YouTubeStudioParityPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->user = $this->admin([
            'name' => 'Delia Lazarescu',
            'email' => 'delia@example.com',
            'password' => 'secret123',
        ]);
        $this->actingAs($this->user);

        SocialAccount::create([
            'platform' => 'youtube',
            'status' => 'connected',
            'access_token' => 'fake-test-token',
        ]);
    }

    // ==========================================
    // Pillar 1: Audio Loudness & Ad Suitability
    // ==========================================

    public function test_audio_loudness_analyzer_evaluates_lufs_and_true_peak(): void
    {
        $analyzer = app(AudioLoudnessAnalyzer::class);

        // Optimal broadcast level
        $resPass = $analyzer->formatResult(-14.0, -1.0);
        $this->assertSame('pass', $resPass['status']);
        $this->assertFalse($resPass['has_clipping']);
        $this->assertStringContainsString('Optimal loudness', $resPass['summary']);

        // Too quiet (-22 LUFS)
        $resQuiet = $analyzer->formatResult(-22.0, -2.5);
        $this->assertSame('warning', $resQuiet['status']);
        $this->assertStringContainsString('boost', $resQuiet['recommendation']);

        // Clipping risk (> -1.0 dBTP)
        $resClip = $analyzer->formatResult(-13.5, 0.4);
        $this->assertTrue($resClip['has_clipping']);
        $this->assertSame('warning', $resClip['status']);
        $this->assertStringContainsString('clipping', $resClip['summary']);
    }

    public function test_ad_suitability_shield_detects_guidelines_and_monetization_rating(): void
    {
        $shield = app(AdSuitabilityShield::class);

        // Clean video
        $clean = $shield->audit([
            'title' => 'Building Event-Driven Systems in Go and Kafka',
            'description' => 'A deep dive into distributed architecture benchmarks.',
            'body' => 'We benchmarked latency across 100k requests per second.',
        ]);
        $this->assertSame('green', $clean['monetization_rating']);
        $this->assertSame(100, $clean['monetization_score']);
        $this->assertEmpty($clean['flagged_guidelines']);

        // Profanity in title triggers heavy penalty
        $vulgar = $shield->audit([
            'title' => 'Why microservices are total shit for startups',
            'description' => 'A blunt rant on architecture.',
        ]);
        $this->assertSame('yellow', $vulgar['monetization_rating']);
        $this->assertLessThanOrEqual(70, $vulgar['monetization_score']);
        $this->assertNotEmpty($vulgar['flagged_guidelines']);
        $this->assertStringContainsString('Language', $vulgar['flagged_guidelines'][0]['guideline']);

        // Sponsor brand deal check
        $deal = Deal::create([
            'brand_name' => 'Datadog',
            'stage' => 'contracted',
            'fee' => 5000,
        ]);

        // Missing sponsor disclosure
        $missingDisclosure = $shield->audit([
            'title' => 'Observability in Kubernetes',
            'description' => 'How to trace p99 latency spikes.',
            'deal_id' => $deal->id,
        ]);
        $this->assertTrue($missingDisclosure['has_paid_promotion']);
        $this->assertFalse($missingDisclosure['paid_watermark_synced']);

        // With proper disclosure
        $withDisclosure = $shield->audit([
            'title' => 'Observability in Kubernetes',
            'description' => 'Sponsored by Datadog. Check out the link below.',
            'deal_id' => $deal->id,
        ]);
        $this->assertTrue($withDisclosure['has_paid_promotion']);
        $this->assertTrue($withDisclosure['paid_watermark_synced']);
    }

    public function test_pre_flight_suite_persists_check_model(): void
    {
        $suite = app(PreFlightSuite::class);
        $script = Script::create([
            'title' => 'Scalable Kafka Partitioning',
            'body' => 'Sponsored by Confluent. Here is how partitions balance load.',
        ]);

        $check = $suite->run([
            'script_id' => $script->id,
            'title' => $script->title,
            'description' => 'Sponsored by Confluent.',
            'resolution' => '3840x2160',
            'fps' => 60.0,
        ]);

        $this->assertInstanceOf(VideoCheck::class, $check);
        $this->assertSame(-14.0, $check->integrated_lufs);
        $this->assertSame('pass', $check->lufs_status);
        $this->assertSame('green', $check->monetization_rating);
        $this->assertTrue($check->technical_notes['is_uhd_ready']);
        $this->assertSame(60.0, $check->fps);

        // API Endpoint test
        $res = $this->postJson('/api/youtube/pre-flight', [
            'script_id' => $script->id,
            'title' => 'New Clean Title',
            'description' => 'Description test',
        ])->assertOk();

        $res->assertJsonPath('check.monetization_rating', 'green');
    }

    // ==========================================
    // Pillar 2: Bayesian Multi-Variant Testing
    // ==========================================

    public function test_bayesian_ab_optimizer_starts_test_with_control_and_challengers(): void
    {
        $optimizer = app(BayesianAbOptimizer::class);

        $test = $optimizer->startTest('yt_test_vid_123', 'Control Title A', null, [
            [
                'title' => 'Challenger Title B',
            ],
            [
                'title' => 'Challenger Title C',
            ],
        ]);

        $this->assertInstanceOf(VideoAbTest::class, $test);
        $this->assertSame('running', $test->status);
        $this->assertCount(3, $test->variants);
        $this->assertSame('Control Title A', $test->variants[0]['title']);
        $this->assertTrue($test->variants[0]['is_control']);
        $this->assertSame('Challenger Title B', $test->variants[1]['title']);
    }

    public function test_bayesian_ab_optimizer_records_performance_and_computes_win_probability(): void
    {
        $optimizer = app(BayesianAbOptimizer::class);

        $test = $optimizer->startTest('yt_test_vid_456', 'Control A', null, [
            [
                'title' => 'Challenger B',
            ],
        ]);

        // Challenger B clearly outperforms Control A
        $updated = $optimizer->recordPerformance($test, [
            [
                'variant_id' => 0,
                'impressions' => 500,
                'clicks' => 15,
                'watch_time_sec' => 3000,
            ],  // 3% CTR
            [
                'variant_id' => 1,
                'impressions' => 500,
                'clicks' => 65,
                'watch_time_sec' => 18000,
            ], // 13% CTR
        ]);

        $eval = $optimizer->evaluate($updated);

        $this->assertTrue($eval['has_winner']);
        $this->assertSame(1, $eval['winner_index']);
        $this->assertGreaterThanOrEqual(95.0, $eval['max_win_probability']);
        $this->assertSame('concluded', $updated->fresh()->status);
        $this->assertSame(1, $updated->fresh()->winner_index);
    }

    public function test_ab_test_api_endpoints_workflow(): void
    {
        // 1. Start test via API
        $resStart = $this->postJson('/api/youtube/ab-tests', [
            'video_id' => 'yt_api_vid_789',
            'original_title' => 'Original Title A',
            'variants' => [
                [
                    'title' => 'Challenger Variant B',
                ],
            ],
        ])->assertCreated();

        $testId = $resStart->json('ab_test.id');
        $this->assertNotNull($testId);

        // 2. Ingest performance observations
        $resPerf = $this->postJson("/api/youtube/ab-tests/{$testId}/performance", [
            'observations' => [
                [
                    'variant_id' => 0,
                    'impressions' => 150,
                    'clicks' => 6,
                    'watch_time_sec' => 1000,
                ],
                [
                    'variant_id' => 1,
                    'impressions' => 150,
                    'clicks' => 18,
                    'watch_time_sec' => 3500,
                ],
            ],
        ])->assertOk();

        $this->assertNotEmpty($resPerf->json('ab_test.statistical_summary'));

        // 3. Conclude manually
        $this->postJson("/api/youtube/ab-tests/{$testId}/conclude", [
            'winner_index' => 1,
        ])->assertOk()->assertJsonPath('ab_test.status', 'concluded')
            ->assertJsonPath('ab_test.winner_index', 1);

        // 4. Index endpoint lists checks and ab_tests
        $indexRes = $this->getJson('/api/youtube/studio')->assertOk();
        $this->assertArrayHasKey('ab_tests', $indexRes->json());
        $this->assertArrayHasKey('checks', $indexRes->json());
    }
}
