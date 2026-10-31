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
    try {
        $command->info($work());
        Setting::put("last_run_{$name}", now()->toIso8601String());
        Setting::put("last_error_{$name}", null);

        return 0;
    } catch (Throwable $e) {
        Setting::put("last_error_{$name}", mb_substr(now()->toIso8601String().' '.$e->getMessage(), 0, 300));
        $command->error($e->getMessage());

        return 1;
    }
};

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

        return "Backed up to storage/app/backups/{$b['name']} (".round($b['bytes'] / 1024).' KB). Keeping the newest '.Backup::KEEP.'.';
    });
})->purpose('Copy the studio database to storage/app/backups (keeps the newest 14)');

Artisan::command('studio:stats', function (VideoStats $stats) use ($track) {
    return $track($this, 'stats', fn () => 'Recorded today’s numbers for '.$stats->snapshot().' of your videos.');
})->purpose('Record today’s views, likes and comments for her recent videos');

// ---- Schedule (her timezone; the whole schedule can be paused in Settings) ----

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
Schedule::command('studio:memory')->dailyAt('03:30')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:clips')->everyTenMinutes()->when(fn () => rescue(fn () => ClipProject::where('status', 'processing')->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:broll')->everyTwoMinutes()->when(fn () => rescue(fn () => VideoJob::whereIn('status', ['queued', 'running'])->exists(), false, false))->withoutOverlapping();
Schedule::command('studio:backup')->dailyAt('03:00')->timezone($tz)->withoutOverlapping();
Schedule::command('studio:stats')->dailyAt('06:30')->timezone($tz)->when($enabled)->withoutOverlapping();
Schedule::command('studio:prune')->dailyAt('04:00')->timezone($tz)->withoutOverlapping();
