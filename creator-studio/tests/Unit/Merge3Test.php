<?php

namespace Tests\Unit;

use App\Domains\Scripts\Merge3;
use PHPUnit\Framework\TestCase;

class Merge3Test extends TestCase
{
    private const BASE = "## Cold open\nLine one\nLine two\n## Chapter\nLine three\nLine four\n## CTA\nSubscribe";

    public function test_separate_edits_both_survive(): void
    {
        $mine = str_replace('Line one', 'Line ONE (mine)', self::BASE);
        $theirs = str_replace('Line four', 'Line FOUR (theirs)', self::BASE);
        $r = Merge3::merge(self::BASE, $mine, $theirs);
        $this->assertSame(0, $r['conflicts']);
        $this->assertStringContainsString('Line ONE (mine)', $r['text']);
        $this->assertStringContainsString('Line FOUR (theirs)', $r['text']);
        $this->assertSame(8, substr_count($r['text'], "\n") + 1);
    }

    public function test_insertions_and_deletions_merge(): void
    {
        $mine = str_replace("Line two\n", "Line two\nInserted by me\n", self::BASE);
        $theirs = str_replace("## CTA\nSubscribe", '## CTA', self::BASE);
        $r = Merge3::merge(self::BASE, $mine, $theirs);
        $this->assertSame(0, $r['conflicts']);
        $this->assertStringContainsString('Inserted by me', $r['text']);
        $this->assertStringNotContainsString('Subscribe', $r['text']);
    }

    public function test_same_line_changed_both_ways_keeps_both(): void
    {
        $mine = str_replace('Line three', 'Mine three', self::BASE);
        $theirs = str_replace('Line three', 'Their three', self::BASE);
        $r = Merge3::merge(self::BASE, $mine, $theirs);
        $this->assertSame(1, $r['conflicts']);
        $this->assertStringContainsString("Mine three\nTheir three", $r['text']);
    }

    public function test_identical_changes_are_not_duplicated(): void
    {
        $both = str_replace('Line two', 'Same fix', self::BASE);
        $r = Merge3::merge(self::BASE, $both, str_replace('Line one', 'Other', $both));
        $this->assertSame(1, substr_count($r['text'], 'Same fix'));
        $this->assertSame(0, $r['conflicts']);
    }

    public function test_trivial_cases(): void
    {
        $this->assertSame('x', Merge3::merge('a', 'x', 'a')['text']);
        $this->assertSame('y', Merge3::merge('a', 'a', 'y')['text']);
        $this->assertSame('', Merge3::merge('', '', '')['text']);
    }
}
