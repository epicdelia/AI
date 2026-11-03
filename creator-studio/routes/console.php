<?php

use App\Domains\Audience\VideoStats;
use App\Domains\Backup\Backup;
use App\Domains\Feeds\FeedReader;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
use App\Domains\Media\BrollGenerator;
use App\Domains\Media\ClipProject;
use App\Domains\Media\VideoJob;
use App\Domains\Media\ShortsClipper;
use App\Domains\Memory\Memory;
use App\Domains\Memory\MemoryChunk;
use App\Domains\Agents\AgentLearner;
use App\Domains\Agents\AgentPlanner;
use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Agents\Publisher;
use App\Domains\Engagement\Engagement;
use App\Domains\Social\Connect\Connections;
use App\Domains\Workflows\Engine as WorkflowEngine;
use App\Domains\Workflows\Models\Workflow;
use App\Domains\Workflows\Models\WorkflowRun;
use App\Domains\Social\Models\SocialAccount;
use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use App\Services\BreakoutScanner;
use App\Services\WeeklyCheckin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Studio jobs. Each one can be run by hand (php artisan studio:scan) and is also scheduled
| below, the way MagicAI's Console\Kernel schedules its clear-up and check commands.
| The schedule only runs while `php artisan schedule:work` (or a cron calling
| `schedule:run` every minute) is running.
*/

/** Run a job, remember when it last ran (or why it failed) for the Settings page. Returns the exit code. */
$track = function ($command, string $name, callable $work): int {
    // AI calls made by this job count against the daily automation budget (Settings → Automation).
    \App\Services\Llm::$automation = $name;
    try {
        $command->info($work());
        Setting::put("last_run_{$name}", now()->toIso8601String());
        Setting::put("last_error_{$name}", null);

        return 0;
    } catch (Throwable $e) {
        Setting::put("last_error_{$name}", mb_substr(now()->toIso8601String().' '.$e->getMessage(), 0, 300));
        $command->error($e->getMessage());

        return 1;
    } finally {
        \App\Services\Llm::$automation = null;
    }
};

Artisan::command('studio:community-digest', function () use ($track) {
    return $track($this, 'community-digest', fn () => 'Community digest sent to '.\App\Domains\Community\Community::sendDigests().' member(s).');
})->purpose('Email members what is new in the Community Hub this week');

Artisan::command('studio:team-reminders', function () use ($track) {
    return $track($this, 'team-reminders', fn () => 'Reminded '.\App\Domains\Team\TeamTasks::remindDue().' team member(s) about tasks due.');
})->purpose('Email team members their tasks due tomorrow or overdue');

Artisan::command('studio:scan', function (BreakoutScanner $scanner) use ($track) {
    return $track($this, 'scan', fn () => 'Found '.$scanner->scan().' new breakout video(s).');
})->purpose('Scan the competitor watchlist for breakout videos');

Artisan::command('studio:feeds', function (FeedReader $reader) use ($track) {
    return $track($this, 'feeds', fn () => 'Feeds refreshed: '.Feed::where('active', true)->get()->sum(fn ($f) => $reader->refresh($f)).' new item(s).');
})->purpose('Refresh news & blog feeds');

Artisan::command('studio:checkin', function (WeeklyCheckin $checkin) use ($track) {
    return $track($this, 'checkin', function () use ($checkin) {
        $verdict = $checkin->run()['verdict'];

        return 'Check-in complete.'.($verdict ? "\n\n".$verdict : '');
    });
})->purpose('Score the weekly plan from real uploads and get the mentor verdict');

Artisan::command('studio:pipeline', function (\App\Domains\Deals\MorningPipeline $pipeline) use ($track) {
    return $track($this, 'pipeline', function () use ($pipeline) {
        $r = $pipeline->run();

        return "Pipeline: {$r['calls']} call(s) in the next 24 h, {$r['prepped']} prep brief(s) written, {$r['follow_ups']} follow-up(s) due.";
    });
})->purpose('Prep briefs for the next 24 hours of intro calls, and the morning follow-up briefing');

Artisan::command('studio:memory {--fresh : Drop the index first (after changing the embeddings provider)}', function (Memory $memory) use ($track) {
    if ($this->option('fresh')) {
        MemoryChunk::query()->delete();
    }

    return $track($this, 'memory', fn () => 'Memory indexed: '.$memory->reindexAll().' chunk(s) written.');
})->purpose('Index scripts, transcripts, Library items, notes and ideas for Memory search');

Artisan::command('studio:clips', function (ShortsClipper $clipper) use ($track) {
    return $track($this, 'clips', function () use ($clipper) {
        $pending = ClipProject::where('status', 'processing')->get();
        foreach ($pending as $p) {
            if ($p->created_at->lt(now()->subDay())) {
                $p->update(['status' => 'failed', 'error' => 'Vizard didn’t finish within 24 hours.']);

                continue;
            }
            $clipper->refresh($p);
        }

        return 'Checked '.$pending->count().' Shorts project(s); '.ClipProject::where('status', 'done')->whereIn('id', $pending->pluck('id'))->count().' finished.';
    });
})->purpose('Collect finished Shorts from Vizard');

Artisan::command('studio:broll', function (BrollGenerator $gen) use ($track) {
    return $track($this, 'broll', function () use ($gen) {
        $pending = VideoJob::whereIn('status', ['queued', 'running'])->get();
        foreach ($pending as $job) {
            if ($job->created_at->lt(now()->subHours(6))) {
                $job->update(['status' => 'failed', 'error' => 'fal.ai didn’t finish within 6 hours.']);

                continue;
            }
            $gen->refresh($job);
        }

        return 'Checked '.$pending->count().' b-roll job(s); '.VideoJob::where('status', 'done')->whereIn('id', $pending->pluck('id'))->count().' finished.';
    });
})->purpose('Collect finished b-roll clips from fal.ai');

Artisan::command('studio:prune', function () use ($track) {
    return $track($this, 'prune', function () {
        // Like MagicAI's app:clear-* commands: keep the database small.
        $usage = AiUsage::where('created_at', '<', now()->subDays(180))->delete();
        $items = FeedItem::where('used', true)->where('created_at', '<', now()->subDays(60))->delete();
        $files = 0;
        foreach (glob(storage_path('app/private/recordings/*')) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400 && @unlink($file)) {
                $files++; // leftovers from a transcription that crashed mid-way
            }
        }

        return "Pruned {$usage} usage row(s), {$items} old feed item(s), {$files} leftover upload(s).";
    });
})->purpose('Remove old usage logs, used feed items and leftover uploads');

Artisan::command('studio:backup', function () use ($track) {
    return $track($this, 'backup', function () {
        $b = Backup::run();

        return "Backed up to storage/app/backups/{$b['name']} (".round($b['bytes'] / 1024).' KB). Keeping the newest '.Backup::KEEP.'.'
            .($b['offsite'] ? " Encrypted copy: {$b['offsite']}." : ' No off-server copy (set BACKUP_DISK).');
    });
})->purpose('Back up the studio database to storage/app/backups (keeps the newest 14), plus an encrypted copy on BACKUP_DISK');

Artisan::command('studio:backup-decrypt {file : an encrypted .enc backup} {out? : where to write it (default: the same name without .enc)}', function () {
    $in = (string) $this->argument('file');
    if (! is_file($in)) {
        $this->error("No such file: {$in}");

        return 1;
    }
    $out = (string) ($this->argument('out') ?: preg_replace('/\.enc$/', '', $in));
    file_put_contents($out, Backup::decrypt((string) file_get_contents($in)));
    $this->info("Decrypted to {$out}.");

    return 0;
})->purpose('Decrypt an off-server backup (needs the same BACKUP_PASSPHRASE or APP_KEY)');

Artisan::command('studio:stats', function (VideoStats $stats) use ($track) {
    return $track($this, 'stats', fn () => 'Recorded today’s numbers for '.$stats->snapshot().' of your videos.');
})->purpose('Record today’s views, likes and comments for her recent videos');

Artisan::command('studio:landing', function () use ($track) {
    return $track($this, 'landing', function () {
        $feed = \App\Domains\Landing\LandingFeed::refresh();
        $live = array_keys(array_filter($feed['sources'], fn ($s) => $s === 'live'));

        return 'Landing page feed rebuilt: '.array_sum(array_map('count', $feed['platforms'])).' items'
            .($live ? ' (live: '.implode(', ', $live).')' : ' (no live content yet)').'.';
    });
})->purpose('Rebuild the public landing page content feed from her latest videos, posts and follower counts');

Artisan::command('studio:social-sync', function () use ($track) {
    return $track($this, 'social', function () {
        $results = Connections::syncAll();
        if (! $results) {
            return 'No connected accounts to sync.';
        }
        $failed = array_filter($results, fn ($r) => ! $r['ok']);
        $lines = implode("
", array_map(fn ($r) => $r['message'], $results));
        if (count($failed) === count($results)) {
            throw new RuntimeException($lines);
        }

        return $lines;
    });
})->purpose('Pull follower counts and post numbers from her connected accounts');

Artisan::command('studio:agents', function (AgentPlanner $planner) use ($track) {
    return $track($this, 'agents', function () use ($planner) {
        AgentPlanner::assertReady();
        $lines = [];
        foreach (SocialAgent::where('active', true)->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))->get() as $agent) {
            try {
                $lines[] = "{$agent->name}: ".count($planner->run($agent)).' post(s) planned.';
            } catch (Throwable $e) {
                AgentEvent::log('failed', "Couldn’t plan posts: {$e->getMessage()}", $agent->id);
                $lines[] = "{$agent->name}: {$e->getMessage()}";
            }
        }

        return $lines ? implode("\n", $lines) : 'No agent was due.';
    });
})->purpose('Let each active agent top up its next 7 days of posts');

Artisan::command('studio:publish', function () use ($track) {
    return $track($this, 'publish', function () {
        $r = Publisher::due();

        return "Published {$r['published']} agent post(s); {$r['failed']} failed.";
    });
})->purpose('Publish approved agent posts whose time has come (LinkedIn, Threads, X)');

Artisan::command('studio:agents-learn', function (AgentLearner $learner) use ($track) {
    return $track($this, 'learn', function () use ($learner) {
        $lines = [];
        foreach (SocialAgent::where('active', true)->get() as $agent) {
            [$m, $e, $r] = AgentLearner::signals($agent);
            if ($m->count() + $e->count() + $r->count() < AgentLearner::MIN_SIGNALS) {
                continue;
            }
            try {
                $lines[] = "{$agent->name}: ".count($learner->learn($agent)).' lesson(s).';
            } catch (Throwable $ex) {
                $lines[] = "{$agent->name}: {$ex->getMessage()}";
            }
        }

        return $lines ? implode("\n", $lines) : 'No agent has enough new results to learn from yet.';
    });
})->purpose('Agents review their results and her edits, and update their lessons');

Artisan::command('studio:engage', function (Engagement $engagement) use ($track) {
    return $track($this, 'engage', function () use ($engagement) {
        $r = $engagement->poll();
        if ($r['errors'] && ! $r['new']) {
            throw new RuntimeException(implode(' ', $r['errors']));
        }

        return "{$r['new']} new comment(s)/DM(s), {$r['sent']} answered automatically, {$r['drafted']} drafted.";
    });
})->purpose('Pull new comments and DMs and run her automations');

Artisan::command('studio:workflows', function (WorkflowEngine $engine) use ($track) {
    return $track($this, 'workflows', function () use ($engine) {
        $fired = WorkflowEngine::tick();

        return "Started {$fired} scheduled workflow(s); ran ".$engine->drain().' queued or waiting run(s).';
    });
})->purpose('Start scheduled workflows and run queued or waiting workflow steps');

Artisan::command('studio:password {email? : the account email (defaults to the only account)}', function () {
    $user = $this->argument('email') ? \App\Models\User::where('email', $this->argument('email'))->first() : \App\Models\User::orderBy('id')->first();
    if (! $user) {
        $this->error('No such account.');

        return 1;
    }
    $password = $this->secret("New password for {$user->email} (at least 8 characters)");
    if (strlen((string) $password) < 8 || $password !== $this->secret('Type it again')) {
        $this->error('Too short, or the two didn’t match. Nothing changed.');

        return 1;
    }
    $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($password), 'remember_token' => null])->save();
    $this->info('Password changed. Sign in with the new one.');

    return 0;
})->purpose('Reset the studio login password (the “Forgot password?” way, from this computer)');

Artisan::command('studio:youtube', function () use ($track) {
    return $track($this, 'youtube', function () {
        $up = \App\Domains\YouTube\YouTubeStudio::drainUploads(50);
        $t = \App\Domains\YouTube\YouTubeStudio::tickTests();

        return "{$up} upload(s) finished, {$t} title test step(s).";
    });
})->purpose('Send queued uploads to YouTube and rotate title tests');

// ---- Schedule (her timezone). Settings → Automation pauses every job that posts, replies,
// runs workflows or spends AI on its own; backups, clean-up and her own clip/b-roll jobs keep going. ----

try {
    $tz = Setting::get('timezone', 'Europe/London') ?: 'Europe/London';
} catch (Throwable) {
    $tz = 'Europe/London'; // before the first migration there's no settings table
}
$enabled = function (): bool {
    try {
        return Setting::get('schedule_enabled', '1') === '1';
    } catch (Throwable) {
        return false;
    }
};

// Heartbeat so Settings can tell whether the scheduler is actually running.
Schedule::call(fn () => Setting::put('scheduler_heartbeat', now()->toIso8601String()))->everyFiveMinutes()->name('heartbeat');

Schedule::command('studio:scan')->dailyAt('07:00')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:feeds')->everyThreeHours()->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:checkin')->weeklyOn(1, '08:00')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:pipeline')->dailyAt('08:15')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:memory')->dailyAt('03:30')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:clips')->everyTenMinutes()->when(fn () => rescue(fn () => ClipProject::where('status', 'processing')->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:broll')->everyTwoMinutes()->when(fn () => rescue(fn () => VideoJob::whereIn('status', ['queued', 'running'])->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:backup')->dailyAt('03:00')->timezone($tz)->withoutOverlapping();
Schedule::command('studio:stats')->dailyAt('06:30')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:landing')->hourlyAt(20)->when($enabled)->withoutOverlapping(); // after social-sync, so new posts and followers show up
Schedule::command('studio:social-sync')->hourlyAt(15)->when(fn () => $enabled() && rescue(fn () => SocialAccount::exists(), false, false))->withoutOverlapping();
Schedule::command('studio:agents')->hourlyAt(5)->when(fn () => $enabled() && rescue(fn () => SocialAgent::where('active', true)->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:publish')->everyFiveMinutes()->when(fn () => $enabled() && rescue(fn () => \App\Domains\Social\Models\SocialPost::where('approval', 'approved')->where('status', 'scheduled')->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:agents-learn')->weeklyOn(0, '20:00')->timezone($tz)->when(fn () => $enabled() && rescue(fn () => SocialAgent::exists(), false, false))->withoutOverlapping();
Schedule::command('studio:engage')->everyTenMinutes()->when(fn () => $enabled() && rescue(fn () => SocialAccount::whereIn('platform', ['instagram', 'threads', 'youtube'])->where('status', 'connected')->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:workflows')->everyMinute()->when(fn () => $enabled() && rescue(fn () => Workflow::where('active', true)->exists() || WorkflowRun::whereIn('status', ['queued', 'waiting'])->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:youtube')->everyMinute()->when(fn () => $enabled() && rescue(fn () => \App\Domains\YouTube\VideoUpload::whereIn('status', ['queued', 'uploading'])->exists() || \App\Domains\YouTube\TitleTest::where('status', 'running')->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:community-digest')->weeklyOn(4, '09:00')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:team-reminders')->dailyAt('08:30')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:prune')->dailyAt('04:00')->timezone($tz)->withoutOverlapping();
