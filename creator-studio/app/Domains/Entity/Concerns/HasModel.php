<?php

declare(strict_types=1);

namespace App\Domains\Entity\Concerns;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Models\Entity;
use App\Helpers\Classes\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait HasModel
{
    use HasStatus;

    public function model(?bool $fresh = false): Builder|Model|null
    {
        return $this->getEntity($fresh)?->firstWhere('key.value', value: $this->name());
    }

    private function getEntity(?bool $fresh = false): ?Collection
    {
        $ttl = Helper::appIsNotDemo() ? 10 : 600;

        if ($fresh) {
            $validEngines = collect(EntityEnum::cases())->pluck('value');
            Entity::whereNotIn('key', $validEngines)->delete();

            return Entity::whereIn('key', $validEngines)->get();
        }

        return Cache::remember('entities', $ttl, static function () {
            $validEngines = collect(EntityEnum::cases())->pluck('value');
            Entity::whereNotIn('key', $validEngines)->delete();

            return Entity::whereIn('key', $validEngines)->get();
        });
    }
}
