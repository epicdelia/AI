<?php

namespace App\Domains\Social;

use App\Domains\Social\Models\SocialPost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Example posts for every network, so she can see how the calendar, previews and analytics
 * look before her own posts and numbers are in. Everything is tagged source=demo and removed
 * in one go; demo follower counts are only added for networks with no real counts.
 */
class DemoContent
{
    public static function count(): int
    {
        return SocialPost::where('source', 'demo')->count();
    }

    /**
     * @return int posts created (0 if the demo is already loaded)
     */
    public static function seed(): int
    {
        if (self::count() > 0) {
            return 0;
        }
        $tz = SocialAnalytics::tz();
        $now = CarbonImmutable::now($tz)->setTime(0, 0);
        $at = fn (int $days, string $time) => $now->addDays($days)->setTimeFromTimeString($time)->utc();
        $m = fn (int $imp, int $likes, int $comments, int $shares = 0, int $saves = 0) => compact('likes', 'comments', 'shares', 'saves') + [
            'impressions' => $imp,
        ];

        $posts = [
            // LinkedIn
            ['linkedin', 'post', 'I let AI answer my email for a week', "I let an AI answer my email for a week.\n\nIt archived my landlord, replied to my mum and booked me into a meeting I'd been avoiding for a month.\n\nThree rules I'd set from day one:\n1. Rules beat prompts\n2. Read the first 20 replies yourself\n3. Never let it say yes to a call\n\nWould you trust it with yours?", [], -20, '08:30', $m(6400, 210, 48, 12)],
            ['linkedin', 'carousel', '5 AI tools I actually use', 'The 5 AI tools that survived my 30-day test (and the 12 that didn’t).', ['5 AI tools that survived my 30-day test', '1. A note app that writes the summary for you', '2. An email triage assistant with strict rules', '3. A meeting recorder that gives you the action list', '4. A voice-to-text tool for drafts on the go', '5. A research assistant that shows its sources', 'Save this, and follow for the full breakdown'], -9, '09:00', $m(9800, 380, 61, 40, 95)],
            ['linkedin', 'post', 'Why I stopped chasing productivity apps', "I deleted 14 productivity apps last month.\n\nMy output went up.\n\nHere's the boring system that replaced them.", [], 3, '08:30', null, 'scheduled'],
            // Instagram
            ['instagram', 'carousel', 'Desk setup for focus', "The desk setup that finally fixed my focus 👇\n\nSave it for your next reset.\n\n#desksetup #productivity #techtok #wfh #ai", ['My focus desk setup', 'One screen, not three', 'Phone in a drawer, not on the desk', 'A timer you can see', 'Lights warm after 6pm', 'Save this for your next desk reset'], -16, '19:00', $m(12500, 940, 88, 64, 410)],
            ['instagram', 'reel', 'POV: the AI archived my landlord', "POV: you let AI run your inbox and it archives your landlord 💀\n\n#ai #productivity #techhumor", ['[TEXT: Day 1 of letting AI run my inbox]', 'It archived my landlord.', 'My actual landlord.', 'Day 3: it replied to my mum.', 'Follow for day 7'], -4, '18:30', $m(31000, 2400, 190, 310, 120)],
            ['instagram', 'post', 'Studio behind the scenes', "Where the videos get made ✨ what should I film next?\n\n#creatorlife #techcreator", [], 5, '19:00', null, 'scheduled'],
            // X
            ['x', 'thread', 'Inbox experiment thread', '', ['I let an AI run my inbox for 7 days. Here is everything that broke (and the one thing that worked):', 'Day 1: it archived my landlord. Not a great start.', 'Day 3: it replied to my mum. She was delighted. I was not.', 'By Friday it wrote more like me than I do. That was the scary part.', 'Full video on my channel.'], -18, '12:00', $m(18200, 320, 41, 58, 77)],
            ['x', 'post', 'Hot take on AI email', 'Hot take: the best AI email setup is three rules and a weekly check, not a fancy prompt.', [], -6, '13:00', $m(7400, 140, 32, 19, 9)],
            ['x', 'post', 'Question for followers', 'What’s one task you’d never hand to an AI? I’ll go first: anything my landlord sends.', [], 2, '12:30', null, 'scheduled'],
            // TikTok
            ['tiktok', 'video', 'AI inbox day 1', 'POV: AI runs your inbox 📬💀 #ai #techtok #productivity', ['[TEXT: I gave AI my inbox]', 'Day 1 and it already archived my landlord.', 'Day 3: it replied to my mum with a thumbs up.', 'Day 5: it booked a meeting I’d been dodging for a month.', 'Part 2 tomorrow'], -12, '20:00', $m(48000, 5200, 310, 640, 380)],
            ['tiktok', 'photo', '3 apps I deleted', 'Three apps I deleted and didn’t miss 🗑️ #techtok #digitalminimalism', ['3 apps I deleted and didn’t miss', 'The “all-in-one” planner', 'The second note app', 'The focus app that needed its own reminders'], -2, '19:30', $m(22000, 1900, 140, 210, 260)],
            ['tiktok', 'video', 'Desk reset in 60 seconds', '60-second desk reset ⏱️ #desksetup #techtok', ['[TEXT: 60-second desk reset]', 'Clear everything off.', 'Put back only three things.', 'Timer on. Phone away.', 'That’s it.'], 4, '20:00', null, 'scheduled'],
            // Threads
            ['threads', 'post', 'Honest AI take', 'Honest take: most “AI productivity” advice is just regular productivity advice with a robot emoji. The rules still do the work. Agree?', [], -15, '09:30', $m(5200, 260, 74, 15)],
            ['threads', 'thread', 'What I learned from the inbox week', '', ['A week of letting AI answer my email taught me more about my inbox than about AI.', 'Most of my email didn’t need a reply at all. The AI just made that obvious.', 'The scary part wasn’t the mistakes. It was how quickly it started to sound like me.', 'Would you try it for a week?'], -3, '10:00', $m(6100, 300, 92, 22)],
            ['threads', 'post', 'Weekend question', 'Weekend question: what’s the one gadget on your desk you’d keep if you could only keep one?', [], 6, '10:00', null, 'scheduled'],
            // YouTube extras
            ['youtube', 'short', 'The landlord moment', '', ['The AI archived my landlord.', '[TEXT: day 1] I had one job: check its work.', 'I did not check its work.'], -11, '17:00', $m(26000, 1800, 120, 90)],
            ['youtube', 'community', 'Poll: next video', 'Next video: should I let AI run my calendar or my to-do list for a week? Vote below 👇', [], 1, '17:00', null, 'scheduled'],
            // Drafts without a date
            ['linkedin', 'post', 'Draft: the 3 rules', "The three rules that made AI email actually work for me.\n\n(draft)", [], null, null, null, 'draft'],
            ['instagram', 'carousel', 'Draft: AI tools carousel', 'AI tools I tried this month 👇 (draft)', ['AI tools I tried this month', 'Tool 1', 'Tool 2', 'Tool 3'], null, null, null, 'draft'],
            ['x', 'post', 'Idea: AI calendar week', 'Idea: let AI run my calendar for a week and tweet every disaster.', [], null, null, null, 'idea'],
        ];

        foreach ($posts as $p) {
            [$platform, $format, $title, $body, $parts, $days, $time, $metrics] = $p;
            $status = $p[8] ?? 'posted';
            SocialPost::create([
                'platform' => $platform,
                'format' => $format,
                'title' => "Demo · {$title}",
                'body' => $body,
                'parts' => $parts,
                'status' => $status,
                'source' => 'demo',
                'metrics' => $metrics,
                'notes' => 'Demo post: remove all demo posts from the Calendar or Analytics page.',
                'posted_at' => $status === 'posted' ? $at($days, $time) : null,
                'scheduled_for' => $status === 'scheduled' ? $at($days, $time) : null,
            ]);
        }

        // Follower counts, only for networks with none of her own.
        $start = [
            'linkedin' => 3200,
            'instagram' => 8400,
            'x' => 2100,
            'tiktok' => 15600,
            'threads' => 1200,
            'youtube' => 4800,
        ];
        $growth = [
            'linkedin' => 260,
            'instagram' => 540,
            'x' => 90,
            'tiktok' => 2300,
            'threads' => 310,
            'youtube' => 410,
        ];
        foreach ($start as $platform => $count) {
            if (DB::table('follower_snapshots')->where('platform', $platform)->exists()) {
                continue;
            }
            foreach ([30, 20, 10, 0] as $i => $daysAgo) {
                DB::table('follower_snapshots')->insert([
                    'platform' => $platform,
                    'count' => $count + (int)round($growth[$platform] * $i / 3),
                    'recorded_on' => $now->subDays($daysAgo)->toDateString(),
                    'source' => 'demo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return count($posts);
    }

    public static function remove(): int
    {
        DB::table('follower_snapshots')->where('source', 'demo')->delete();

        return SocialPost::where('source', 'demo')->delete();
    }
}
