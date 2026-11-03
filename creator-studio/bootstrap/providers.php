<?php

use App\Providers\AppServiceProvider;
use App\Domains\Engine\EngineServiceProvider;
use App\Domains\Entity\EntityServiceProvider;

return [
    AppServiceProvider::class,
    EngineServiceProvider::class,
    EntityServiceProvider::class,
];
