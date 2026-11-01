<?php

namespace App\Providers;

use App\Domains\Agents\Models\SocialAgent;
use App\Domains\Toolkit\Models\Document;
use App\Jobs\IndexMemory;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Script;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * A single-user local app: after an update brings new migrations, apply them on the next
     * web request, so a new table never shows up as "no such table" in her browser. A cheap
     * fingerprint of the migrations folder decides; a file lock stops two requests racing.
     */
    private function migrateIfNeeded(): void
    {
        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }
        $files = glob(database_path('migrations/*.php')) ?: [];
        $stamp = count($files).':'.basename((string) end($files));
        $marker = storage_path('framework/migrated.txt');
        if (@file_get_contents($marker) === $stamp) {
            return;
        }
        $lock = @fopen($marker.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            return;
        }
        try {
            if (@file_get_contents($marker) !== $stamp) {
                Artisan::call('migrate', ['--force' => true]);
                file_put_contents($marker, $stamp);
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->migrateIfNeeded();

        // agents/studio/<tool>: the AI engines from anywhere in the studio, with her brand and voice
        // but no agent of their own (SocialAgent::studio()). Numbers still bind as usual.
        Route::bind('agent', fn (string $value) => $value === 'studio' ? SocialAgent::studio() : SocialAgent::findOrFail((int) $value));

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // @md($text): render AI/user Markdown safely (raw HTML is escaped, unsafe links dropped).
        Blade::directive('md', fn ($expr) => "<?php echo \Illuminate\Support\Str::markdown((string) ({$expr}), ['html_input' => 'escape', 'allow_unsafe_links' => false]); ?>");

        // Keep Memory in step with her work: re-index after a save, forget after a delete.
        // In a web request this runs after the response is sent; in commands and the scheduler
        // (no response to wait for) it runs straight away.
        $dispatch = fn (...$args) => $this->app->runningInConsole()
            ? IndexMemory::dispatchSync(...$args)
            : IndexMemory::dispatchAfterResponse(...$args);
        foreach ([Script::class, Document::class, Note::class, Idea::class] as $class) {
            $class::saved(function ($model) use ($dispatch) {
                $fields = $model instanceof Script ? ['title', 'body', 'transcript'] : null;
                if ($fields === null || $model->wasRecentlyCreated || $model->wasChanged($fields)) {
                    $dispatch($model::class, $model->getKey());
                }
            });
            $class::deleted(fn ($model) => $dispatch($model::class, $model->getKey(), true));
        }
    }
}
