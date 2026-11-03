<?php

namespace MagicAI\Healthy;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use MagicAI\Healthy\Commands\HealthyCommand;
use MagicAI\Healthy\Http\Controllers\HealthyController;
use MagicAI\Healthy\Http\Middleware\HealthyMiddleware;
use MagicAI\Updater\View\Components\Button;
use MagicAI\Updater\View\Components\Li;
use MagicAI\Updater\View\Components\Permission;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HealthyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('magicai-healthy')
            ->hasConfigFile()
            ->hasViews()
            ->hasViewComponents('healthy', Permission::class, Li::class, Button::class)
            ->hasCommand(HealthyCommand::class);
    }

    public function bootingPackage()
    {
        $this->app->make(Router::class)->aliasMiddleware(
            'healthy',
            HealthyMiddleware::class
        );
    }

    public function packageRegistered(): void
    {
        Route::prefix('healthy')
            ->as('healthy.')
            ->middleware(['api'])
            ->group(function (Router $router) {
                $router->get('check/selected', [HealthyController::class, 'checkSelected'])
                    ->name('check.selected');
                $router->get('check/all', [HealthyController::class, 'checkAll'])
                    ->name('check.all');
            });
    }
}
