<?php

namespace App\Providers;

use App\Domains\Toolkit\Models\Document;
use App\Jobs\IndexMemory;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Script;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
