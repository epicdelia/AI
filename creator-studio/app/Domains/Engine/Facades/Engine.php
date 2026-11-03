<?php

declare(strict_types=1);

namespace App\Domains\Engine\Facades;

use App\Domains\Engine\BaseDriver;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin \App\Domains\Engine\Engine
 * @mixin BaseDriver
 */
class Engine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.engine';
    }
}
