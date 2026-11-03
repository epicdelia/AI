<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionDriverAvailable
{
    /**
     * Cache whether the session table has been confirmed to exist
     * to avoid redundant schema queries across requests in long-running processes.
     */
    private static ?bool $sessionTableExists = null;

    /**
     * Reset cached table check (e.g. after running migrations or during tests).
     */
    public static function resetTableCheck(): void
    {
        self::$sessionTableExists = null;
    }

    /**
     * Handle an incoming request.
     *
     * If the configured session driver is "database" but the database
     * is not yet reachable or the sessions table has not yet been migrated,
     * gracefully switch session.driver to "file" (or "array") so the request
     * does not crash with "no such table: sessions".
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Config::get('session.driver') === 'database') {
            if (self::$sessionTableExists !== true) {
                $table = Config::get('session.table', 'sessions');
                $connection = Config::get('session.connection');

                $exists = false;
                try {
                    $exists = Schema::connection($connection)->hasTable($table);
                } catch (\Throwable) {
                    $exists = false;
                }

                if ($exists) {
                    self::$sessionTableExists = true;
                } else {
                    $sessionsPath = storage_path('framework/sessions');
                    if (!File::isDirectory($sessionsPath)) {
                        @File::makeDirectory($sessionsPath, 0755, true);
                    }

                    $fallback = (is_dir($sessionsPath) && is_writable($sessionsPath)) ? 'file' : 'array';
                    Config::set('session.driver', $fallback);

                    if (app()->resolved('session')) {
                        try {
                            app('session')->forgetDrivers();
                        } catch (\Throwable) {
                            // ignore if not supported
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
