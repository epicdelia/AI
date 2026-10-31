<?php

namespace App\Domains\Engine;

use App\Domains\Engine\Contracts\EngineDriver;
use App\Domains\Engine\Drivers\AnthropicDriver;
use App\Domains\Engine\Drivers\OpenAiCompatibleDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;
use Illuminate\Support\Manager;

/**
 * Resolves the AI driver chosen in Settings: app(EngineManager::class)->driver().
 * Drivers aren't cached between calls, so a Settings change applies on the next request.
 */
class EngineManager extends Manager
{
    public function current(): EngineEnum
    {
        $saved = Setting::get('llm_engine', '');

        return EngineEnum::tryFrom((string) $saved) ?? EngineEnum::fromBaseUrl(Setting::get('llm_base_url'));
    }

    public function getDefaultDriver(): string
    {
        return $this->current()->value;
    }

    public function driver($driver = null): EngineDriver
    {
        $engine = $driver instanceof EngineEnum ? $driver : EngineEnum::from($driver ?? $this->getDefaultDriver());

        return $engine === EngineEnum::ANTHROPIC ? new AnthropicDriver($engine) : new OpenAiCompatibleDriver($engine);
    }
}
