<?php

use App\Domains\Feeds\FeedReader;
use App\Domains\Feeds\Models\Feed;
use App\Domains\Feeds\Models\FeedItem;
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
Schedule::command('studio:prune')->dailyAt('04:00')->timezone($tz)->withoutOverlapping();
