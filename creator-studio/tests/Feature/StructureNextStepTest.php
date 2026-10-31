<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\Script;
use App\Models\Setting;
use App\Models\User;
use App\Models\Week;
use App\Services\NextStep;
use App\Services\ScriptStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureNextStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flush();
        $this->actingAs(User::create(['name' => 'Delia', 'email' => 'd@example.com', 'password' => 'secret123']));
    }

    /** $n spoken words (150 = one minute). */
    private static function words(int $n): string
    {
        return trim(str_repeat('word ', $n));
    }

    // ---------------------------------------------------------------- structure

    public function test_sections_are_timed_and_good_structure_has_no_flags(): void
    {
        $body = implode("\n", [
            '## Cold open', '[VISUAL: the result]', self::words(60),
            '## Chapter 1: Setup', '[B-ROLL: desk]', self::words(150),
            '## Chapter 2: The test', '[VISUAL: screen]', self::words(150),
            '## Chapter 3: Results', '[VISUAL: chart]', self::words(150),
            '## CTA', self::words(30),
            '## Title options', self::words(500),
        ]);
        $s = ScriptStructure::analyse($body);

        $this->assertSame([], $s['flags']);
        $this->assertSame(['Cold open', 'Chapter 1: Setup', 'Chapter 2: The test', 'Chapter 3: Results', 'CTA'], array_column($s['sections'], 'title'));
        $this->assertSame(24 + 60 + 60 + 60 + 12, $s['total_seconds'], 'the Title options tail is not spoken');
        $this->assertSame([0, 24, 84, 144, 204], array_column($s['sections'], 'start'));
        $this->assertSame("0:00 Cold open\n0:24 Setup\n1:24 The test\n2:24 Results\n3:24 CTA", $s['chapters']);
    }

    public function test_retention_problems_are_flagged_with_lines(): void
    {
        $body = implode("\n", [
            '## Cold open', self::words(120),                    // 48s, and starts a no-visual stretch
            '## The middle', self::words(500),                   // 3:20 in one go, still no visual
            '[YOUR STORY: the first time] and [VERIFY] this',
            '## CTA', self::words(80),                           // 32s
        ]);
        $s = ScriptStructure::analyse($body);
        $kinds = array_column($s['flags'], 'kind');

        foreach (['open', 'long', 'visual', 'cta', 'chapters', 'gaps'] as $kind) {
            $this->assertContains($kind, $kinds, $kind);
        }
        $byKind = array_column($s['flags'], null, 'kind');
        $this->assertSame(1, $byKind['open']['line']);
        $this->assertSame(3, $byKind['long']['line']);
        $this->assertSame(2, $byKind['visual']['line'], 'the stretch starts at the first spoken line');
        $this->assertSame(5, $byKind['gaps']['line']);
        $this->assertSame(['story' => 1, 'verify' => 1], $s['placeholders']);
        $this->assertSame(array_column($s['flags'], 'line'), collect($s['flags'])->sortBy('line')->pluck('line')->all(), 'sorted by line');
        $this->assertSame(500 + 2, $s['sections'][1]['words'], 'bracketed placeholders are not spoken words');
    }

    public function test_short_or_headless_scripts_still_work(): void
    {
        $s = ScriptStructure::analyse(self::words(40));
        $this->assertSame('Intro', $s['sections'][0]['title']);
        $this->assertNull($s['chapters'], 'YouTube needs 3 chapters');
        $this->assertSame([], ScriptStructure::analyse('')['sections']);
    }

    public function test_script_and_lint_endpoints_include_structure(): void
    {
        $script = Script::create(['title' => 'T', 'body' => "## Cold open\n".self::words(20)]);
        $this->getJson("/api/scripts/{$script->id}")->assertOk()->assertJsonPath('structure.sections.0.title', 'Cold open');
        $this->postJson('/api/scripts/lint', ['text' => "## A\n".self::words(10)])->assertOk()->assertJsonPath('structure.sections.0.words', 10);
    }

    // ---------------------------------------------------------------- next step

    private function plan(): Week
    {
        return Week::create(['number' => 1, 'start_date' => now()->subDays(2)->toDateString(), 'due_date' => now()->addDays(4)->toDateString()]);
    }

    public function test_next_step_walks_the_pipeline(): void
    {
        $this->assertSame('ideas', NextStep::current()['key'], 'nothing at all: get ideas');
        $week = $this->plan();
        Idea::create(['title' => 'An idea', 'status' => 'picked']);
        $this->assertSame('pick', NextStep::current()['key']);
        $this->assertStringContainsString('Due in 4 days', NextStep::current()['detail']);

        $script = Script::create(['title' => 'Inbox week', 'body' => 'just a start']);
        $week->update(['script_id' => $script->id]);
        $this->assertSame('draft', NextStep::current()['key']);

        $script->update(['body' => "## Cold open\n".self::words(200)."\nA dash — here.\n[YOUR STORY: why]"]);
        $step = NextStep::current();
        $this->assertSame('lint', $step['key']);
        $this->assertSame("/scripts/{$script->id}", $step['link']);

        $script->update(['body' => "## Cold open\n".self::words(200)."\n[YOUR STORY: why]"]);
        $this->assertSame('review', NextStep::current()['key']);
        $this->assertStringEndsWith('?tab=review', NextStep::current()['link']);

        $script->update(['status' => 'reviewed']);
        $this->assertSame('gaps', NextStep::current()['key']);
        $this->assertStringContainsString('1 [YOUR STORY]', NextStep::current()['title']);

        $script->update(['body' => "## Cold open\n".self::words(200)]);
        $this->assertSame('film', NextStep::current()['key']);
        $this->assertStringContainsString('1:20 to read', NextStep::current()['detail']);

        $script->update(['status' => 'filmed']);
        $this->assertStringEndsWith('?tab=package', NextStep::current()['link']);

        $script->update(['status' => 'published']);
        $this->assertSame('checkin', NextStep::current()['key']);

        $week->update(['status' => 'shipped']);
        $this->assertNull(NextStep::current(), 'shipped and no next week planned');
        Week::create(['number' => 2, 'start_date' => now()->addDays(5)->toDateString(), 'due_date' => now()->addDays(11)->toDateString()]);
        Week::where('number', 1)->update(['due_date' => now()->addDays(4)->toDateString()]);
        $this->assertSame('pick', NextStep::current()['key'], 'shipped: pick next week’s video');
    }

    public function test_without_a_week_link_it_follows_the_latest_script(): void
    {
        $this->plan();
        Script::create(['title' => 'Old', 'body' => self::words(300), 'status' => 'reviewed']);
        $this->travel(1)->minutes();
        $latest = Script::create(['title' => 'Newer', 'body' => 'tiny']);
        $this->assertSame($latest->id, NextStep::current()['script_id']);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('next_step.key', 'draft');
    }
}
