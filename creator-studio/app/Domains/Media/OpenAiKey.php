<?php

namespace App\Domains\Media;

use App\Domains\Engine\EngineManager;
use App\Domains\Engine\Enums\EngineEnum;
use App\Models\Setting;

/**
 * The OpenAI key for OpenAI-only features (read-aloud, thumbnails, Memory embeddings):
 * the dedicated "extra OpenAI key" if set, otherwise the main AI key when OpenAI is the engine.
 */
class OpenAiKey
{
    public static function get(): ?string
    {
        if (Setting::has('embedding_api_key')) {
            return Setting::get('embedding_api_key');
        }

        return app(EngineManager::class)->current() === EngineEnum::OPENAI && Setting::has('llm_api_key')
            ? Setting::get('llm_api_key')
            : null;
    }
}
