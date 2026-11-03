<?php

namespace RachidLaasri\LaravelInstaller\Middleware;

use App\Helpers\Classes\Helper;
use Closure;
use Illuminate\Support\Facades\Schema;

class canInstall
{
    public function handle($request, Closure $next)
    {
        if ($this->alreadyInstalled()) {
            $installedRedirect = config('installer.installedAlreadyAction');

            switch ($installedRedirect) {

                case 'route':
                    $routeName = config('installer.installed.redirectOptions.route.name');
                    $data = config('installer.installed.redirectOptions.route.message');

                    return redirect()->route($routeName)->with(['data' => $data]);

                    break;

                case 'abort':
                    abort(config('installer.installed.redirectOptions.abort.type'));

                    break;

                case 'dump':
                    $dump = config('installer.installed.redirectOptions.dump.data');
                    dd($dump);

                    break;

                case '404':
                case 'default':
                default:
                    abort(404);

                    break;
            }
        }

        return $next($request);
    }

    public function alreadyInstalled(): bool
    {
        if (file_exists(storage_path('installed'))) {
            return true;
        }

        if (! Helper::dbConnectionStatus()) {
            return false;
        }

        try {
            if (Schema::hasTable('users') && \App\Models\User::count() > 0) {
                return true;
            }

            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
