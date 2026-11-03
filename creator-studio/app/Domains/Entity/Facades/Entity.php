<?php

declare(strict_types=1);

namespace App\Domains\Entity\Facades;

use App\Domains\Entity\BaseDriver;
use App\Domains\Entity\EntityManager;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin EntityManager
 * @mixin BaseDriver
 */
class Entity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai.entity';
    }
}
