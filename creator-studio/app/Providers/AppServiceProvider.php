<?php

namespace App\Providers;

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
    }
}
