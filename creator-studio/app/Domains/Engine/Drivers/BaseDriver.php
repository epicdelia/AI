<?php

namespace App\Domains\Engine\Drivers;

use App\Domains\Engine\Contracts\EngineDriver;
use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;
use RuntimeException;

/** Shared config lookup: the engine, base URL, model and key all come from Settings. */
abstract class BaseDriver implements EngineDriver
{
    public function __construct(protected EngineEnum $engine) {}

    public function enum(): EngineEnum
    {
        return $this->engine;
    }

    protected function baseUrl(): string
    {
        $saved = trim((string) Setting::get('llm_base_url', ''));
        $url = $this->engine === EngineEnum::CUSTOM ? $saved : ($saved !== '' && EngineEnum::fromBaseUrl($saved) === $this->engine ? $saved : $this->engine->baseUrl());
        if ($url === '') {
            throw new RuntimeException('Set a base URL for your custom AI provider in Settings.');
        }

        return rtrim($url, '/');
    }

    protected function model(): string
    {
        return Setting::get('llm_model') ?: $this->engine->defaultModel();
    }

    protected function key(): string
    {
        $key = Setting::get('llm_api_key') ?: ($this->engine->canUseAssemblyKey() ? Setting::get('assemblyai_api_key') : null);
        if (! $key) {
            throw new RuntimeException("No {$this->engine->label()} key yet. Add one under Settings.");
        }

        return $key;
    }

    protected static function clip(mixed $msg): string
    {
        return mb_substr(is_string($msg) ? $msg : (string) json_encode($msg), 0, 300);
    }
}
