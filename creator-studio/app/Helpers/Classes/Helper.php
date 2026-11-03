<?php

declare(strict_types=1);

namespace App\Helpers\Classes;

use App\Models\SettingTwo;
use Illuminate\Support\Facades\DB;
use Throwable;

class Helper
{
    /**
     * Check if the database connection is alive and working.
     */
    public static function dbConnectionStatus(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Retrieve a cached or fresh setting value from SettingTwo.
     */
    public static function settingTwo(string $key, mixed $default = null): mixed
    {
        try {
            if (class_exists(SettingTwo::class)) {
                $setting = SettingTwo::getCache();
                if ($setting && isset($setting->{$key})) {
                    return $setting->{$key};
                }

                $val = SettingTwo::query()->value($key);
                if ($val !== null) {
                    return $val;
                }
            }
        } catch (Throwable) {
            // Suppress if table does not exist yet (pre-migration)
        }

        return $default;
    }
}

