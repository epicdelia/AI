<?php

namespace App\Domains\Engine\Enums;

/**
 * Every AI provider the studio can talk to. Most speak the OpenAI chat-completions dialect;
 * Anthropic gets its own driver. Pick one under Settings and the whole app switches over.
 */
enum EngineEnum: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GEMINI = 'gemini';
    case OPENROUTER = 'openrouter';
    case DEEPSEEK = 'deepseek';
    case XAI = 'xai';
    case PERPLEXITY = 'perplexity';
    case TOGETHER = 'together';
    case ASSEMBLYAI = 'assemblyai';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic (Claude)',
            self::GEMINI => 'Google Gemini',
            self::OPENROUTER => 'OpenRouter',
            self::DEEPSEEK => 'DeepSeek',
            self::XAI => 'xAI (Grok)',
            self::PERPLEXITY => 'Perplexity',
            self::TOGETHER => 'Together AI',
            self::ASSEMBLYAI => 'AssemblyAI LLM Gateway',
            self::CUSTOM => 'Custom (OpenAI-compatible)',
        };
    }

    public function baseUrl(): string
    {
        return match ($this) {
            self::OPENAI => 'https://api.openai.com/v1',
            self::ANTHROPIC => 'https://api.anthropic.com/v1',
            self::GEMINI => 'https://generativelanguage.googleapis.com/v1beta/openai',
            self::OPENROUTER => 'https://openrouter.ai/api/v1',
            self::DEEPSEEK => 'https://api.deepseek.com/v1',
            self::XAI => 'https://api.x.ai/v1',
            self::PERPLEXITY => 'https://api.perplexity.ai',
            self::TOGETHER => 'https://api.together.xyz/v1',
            self::ASSEMBLYAI => 'https://llm-gateway.assemblyai.com/v1',
            self::CUSTOM => '',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OPENAI => 'gpt-5-mini',
            self::ANTHROPIC => 'claude-sonnet-5-5',
            self::GEMINI => 'gemini-2.5-flash',
            self::OPENROUTER => 'anthropic/claude-sonnet-5-5',
            self::DEEPSEEK => 'deepseek-chat',
            self::XAI => 'grok-4',
            self::PERPLEXITY => 'sonar',
            self::TOGETHER => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            self::ASSEMBLYAI => 'gpt-5-mini',
            self::CUSTOM => '',
        };
    }

    /**
     * Where to get a key, shown next to the key field in Settings.
     */
    public function keyUrl(): ?string
    {
        return match ($this) {
            self::OPENAI => 'https://platform.openai.com/api-keys',
            self::ANTHROPIC => 'https://console.anthropic.com/settings/keys',
            self::GEMINI => 'https://aistudio.google.com/apikey',
            self::OPENROUTER => 'https://openrouter.ai/keys',
            self::DEEPSEEK => 'https://platform.deepseek.com/api_keys',
            self::XAI => 'https://console.x.ai',
            self::PERPLEXITY => 'https://www.perplexity.ai/settings/api',
            self::TOGETHER => 'https://api.together.ai/settings/api-keys',
            self::ASSEMBLYAI => 'https://www.assemblyai.com/dashboard/api-keys',
            self::CUSTOM => null,
        };
    }

    /**
     * The AssemblyAI gateway can reuse the dictation key, so a separate AI key is optional.
     */
    public function canUseAssemblyKey(): bool
    {
        return $this === self::ASSEMBLYAI;
    }

    /**
     * Guess the engine from a base URL, for installs saved before engines existed.
     */
    public static function fromBaseUrl(?string $url): self
    {
        $url = (string)$url;
        foreach (self::cases() as $case) {
            if ($case !== self::CUSTOM && $case->baseUrl() !== '' && str_starts_with(rtrim($url, '/'), $case->baseUrl())) {
                return $case;
            }
        }
        if (str_contains($url, 'assemblyai.com')) {
            return self::ASSEMBLYAI;
        }

        return $url === '' ? self::OPENAI : self::CUSTOM;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $e) => [
            'value' => $e->value,
            'label' => $e->label(),
            'base_url' => $e->baseUrl(),
            'default_model' => $e->defaultModel(),
            'key_url' => $e->keyUrl(),
        ], self::cases());
    }
}
