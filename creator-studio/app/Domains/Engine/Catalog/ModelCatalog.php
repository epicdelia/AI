<?php

namespace App\Domains\Engine\Catalog;

use App\Domains\Engine\Enums\EngineEnum;

/**
 * Known chat models per engine, ported from MagicAI's Entity domain (EntityEnum):
 * the model ids, isReasoningModel(), supportedReasoningEfforts(), resolveReasoningEffort()
 * and requiresMaxCompletionTokens() rules, plus vision support and deprecation flags.
 *
 * Kept to chat models a creator would pick (MagicAI's enum has 248 entries across image,
 * video, voice and embeddings). Any other model id still works: the catalog only adds
 * labels, request rules and the Settings dropdown.
 */
class ModelCatalog
{
    // Effort ladders, exactly as in EntityEnum::supportedReasoningEfforts().
    private const GPT5_ORIGINAL = ['minimal', 'low', 'medium', 'high'];
    private const GPT5_1_PLUS = ['none', 'low', 'medium', 'high'];
    private const GPT5_PRO = ['medium', 'high', 'xhigh'];
    private const GPT5_5 = ['none', 'low', 'medium', 'high', 'xhigh'];
    private const O_SERIES = ['low', 'medium', 'high'];

    private const EFFORT_ORDER = ['none', 'minimal', 'low', 'medium', 'high', 'xhigh'];

    /**
     * @var array<string, array<string, array>>|null
     */
    private static ?array $merged = null;

    /**
     * id => [label, reasoning efforts (empty = not a reasoning model), vision, deprecated]
     *
     * @return array<string, array{0: string, 1: array<int, string>, 2: bool, 3: bool}>
     */
    public static function models(EngineEnum $engine): array
    {
        return match ($engine) {
            EngineEnum::OPENAI, EngineEnum::ASSEMBLYAI => [
                'gpt-5.5' => ['GPT-5.5', self::GPT5_5, true, false],
                'gpt-5.4' => ['GPT-5.4', self::GPT5_1_PLUS, true, false],
                'gpt-5.4-mini' => ['GPT-5.4 mini', self::GPT5_1_PLUS, true, false],
                'gpt-5.4-nano' => ['GPT-5.4 nano', self::GPT5_1_PLUS, true, false],
                'gpt-5.2' => ['GPT-5.2', self::GPT5_1_PLUS, true, false],
                'gpt-5.2-pro' => ['GPT-5.2 Pro', self::GPT5_PRO, true, false],
                'gpt-5.1' => ['GPT-5.1', self::GPT5_1_PLUS, true, false],
                'gpt-5' => ['GPT-5', self::GPT5_ORIGINAL, true, false],
                'gpt-5-mini' => ['GPT-5 mini', self::GPT5_ORIGINAL, true, false],
                'gpt-5-nano' => ['GPT-5 nano', self::GPT5_ORIGINAL, true, false],
                'gpt-5-pro' => ['GPT-5 Pro', self::GPT5_PRO, true, false],
                'gpt-5-chat-latest' => ['GPT-5 Chat', self::GPT5_ORIGINAL, true, false],
                'gpt-4.1' => ['GPT-4.1', [], true, false],
                'gpt-4.1-mini' => ['GPT-4.1 mini', [], true, false],
                'gpt-4.1-nano' => ['GPT-4.1 nano', [], true, false],
                'gpt-4o' => ['GPT-4o', [], true, false],
                'gpt-4o-mini' => ['GPT-4o mini', [], true, false],
                'o3' => ['o3', self::O_SERIES, true, false],
                'o4-mini' => ['o4-mini', self::O_SERIES, true, false],
                'o3-mini' => ['o3-mini', self::O_SERIES, false, false],
                'o1' => ['o1', self::O_SERIES, true, true],
                'gpt-4-1106-preview' => ['GPT-4 Turbo (preview)', [], false, true],
            ],
            EngineEnum::ANTHROPIC => [
                'claude-opus-5-5' => ['Claude Opus 5.5', [], true, false],
                'claude-sonnet-5-5' => ['Claude Sonnet 5.5', [], true, false],
                'claude-haiku-5-5' => ['Claude Haiku 5.5', [], true, false],
                'claude-fable-5-1' => ['Claude Fable 5.1', [], true, false],
                'claude-fable-5' => ['Claude Fable 5', [], true, false],
                'claude-sonnet-5' => ['Claude Sonnet 5', [], true, false],
                'claude-opus-4-8' => ['Claude Opus 4.8', [], true, false],
                'claude-opus-4-7' => ['Claude Opus 4.7', [], true, false],
                'claude-sonnet-4-6' => ['Claude Sonnet 4.6', [], true, false],
                'claude-sonnet-4-5-20250929' => ['Claude Sonnet 4.5', [], true, false],
                'claude-opus-4-1-20250805' => ['Claude Opus 4.1', [], true, false],
                'claude-sonnet-4-20250514' => ['Claude Sonnet 4', [], true, false],
                'claude-3-5-haiku-20241022' => ['Claude 3.5 Haiku', [], false, true], // MagicAI's id here was wrong ("claude-3-haiku-20241022")
                'claude-3-7-sonnet-20250219' => ['Claude 3.7 Sonnet', [], true, true],
            ],
            EngineEnum::GEMINI => [
                'gemini-3.1-pro-preview' => ['Gemini 3.1 Pro (preview)', [], true, false],
                'gemini-3-pro-preview' => ['Gemini 3 Pro (preview)', [], true, false],
                'gemini-3-flash-preview' => ['Gemini 3 Flash (preview)', [], true, false],
                'gemini-2.5-pro' => ['Gemini 2.5 Pro', [], true, false],
                'gemini-2.5-flash' => ['Gemini 2.5 Flash', [], true, false],
                'gemini-2.0-flash' => ['Gemini 2.0 Flash', [], true, false],
                'gemini-2.0-flash-lite' => ['Gemini 2.0 Flash-Lite', [], true, false],
                'gemini-1.5-pro' => ['Gemini 1.5 Pro', [], true, true],
            ],
            EngineEnum::DEEPSEEK => [
                'deepseek-chat' => ['DeepSeek Chat', [], false, false],
                'deepseek-reasoner' => ['DeepSeek Reasoner', [], false, false],
            ],
            EngineEnum::XAI => [
                'grok-4-0709' => ['Grok 4', [], true, false],
                'grok-4-1-fast-reasoning' => ['Grok 4.1 Fast (reasoning)', [], true, false],
                'grok-4-1-fast-non-reasoning' => ['Grok 4.1 Fast', [], true, false],
                'grok-4-fast-reasoning' => ['Grok 4 Fast (reasoning)', [], true, false],
                'grok-3' => ['Grok 3', [], false, false],
                'grok-3-mini' => ['Grok 3 mini', [], false, false],
                'grok-2-vision-1212' => ['Grok 2 Vision', [], true, true],
            ],
            EngineEnum::PERPLEXITY => [
                'sonar' => ['Sonar', [], false, false],
                'sonar-pro' => ['Sonar Pro', [], false, false],
                'sonar-reasoning' => ['Sonar Reasoning', [], false, false],
            ],
            default => [],
        };
    }

    /**
     * Curated models plus every chat model ported from MagicAI's EntityEnum
     * (magicai_models.php, generated). Curated entries win; MagicAI-only reasoning models get
     * efforts from EntityEnum's own ladders. Same tuple shape as models():
     * [label, efforts, vision, deprecated] plus the full MagicAI label as a 5th "description".
     *
     * @return array<string, array>
     */
    public static function all(EngineEnum $engine): array
    {
        if (self::$merged === null) {
            self::$merged = [];
            $ported = require __DIR__ . '/magicai_models.php';
            foreach (EngineEnum::cases() as $e) {
                $rows = [];
                foreach ($ported[$e->value] ?? [] as $id => [$label, $reasoning, $deprecated]) {
                    $short = trim(preg_split('/\s+\(|\s{2,}|\s+(?=Enhanced|Our |The )/', $label)[0]);
                    $rows[$id] = [mb_strimwidth($short, 0, 48, '…'), $reasoning ? self::laddersFor($id) : [], self::guessVision($e, $id), (bool)$deprecated, $label];
                }
                foreach (self::models($e) as $id => $m) {
                    $rows[$id] = [$m[0], $m[1], $m[2], $m[3], $rows[$id][4] ?? $m[0]];
                }
                self::$merged[$e->value] = $rows;
            }
        }

        return self::$merged[$engine->value] ?? [];
    }

    public static function find(EngineEnum $engine, string $model): ?array
    {
        $m = self::all($engine)[$model] ?? null;
        if ($m === null && $engine === EngineEnum::OPENROUTER && str_contains($model, '/')) {
            // OpenRouter ids are "provider/model": reuse the upstream rules where we know them.
            [$vendor, $id] = explode('/', $model, 2);
            $upstream = [
                'openai' => EngineEnum::OPENAI,
                'anthropic' => EngineEnum::ANTHROPIC,
                'google' => EngineEnum::GEMINI,
                'x-ai' => EngineEnum::XAI,
                'deepseek' => EngineEnum::DEEPSEEK,
            ][$vendor] ?? null;
            $m = $upstream ? (self::all($upstream)[$id] ?? null) : null;
        }

        return $m ? [
            'id' => $model,
            'label' => $m[0],
            'efforts' => $m[1],
            'vision' => $m[2],
            'deprecated' => $m[3],
            'description' => $m[4] ?? $m[0],
        ] : null;
    }

    /**
     * EntityEnum::isReasoningModel(). Unknown OpenAI ids starting with "gpt-5" or "o1".."o9" count too.
     */
    public static function isReasoning(EngineEnum $engine, string $model): bool
    {
        $m = self::find($engine, $model);
        if ($m) {
            return $m['efforts'] !== [];
        }

        return in_array($engine, [EngineEnum::OPENAI, EngineEnum::ASSEMBLYAI], true)
            && (bool)preg_match('/^(gpt-5|o[1-9])/', $model);
    }

    /**
     * EntityEnum::requiresMaxCompletionTokens(): reasoning models reject max_tokens and temperature.
     */
    public static function requiresMaxCompletionTokens(EngineEnum $engine, string $model): bool
    {
        return self::isReasoning($engine, $model);
    }

    public static function supportsVision(EngineEnum $engine, string $model): bool
    {
        $m = self::find($engine, $model);

        return $m ? $m['vision'] : !in_array($engine, [EngineEnum::DEEPSEEK, EngineEnum::PERPLEXITY], true);
    }

    /**
     * Port of EntityEnum::resolveReasoningEffort(): nearest supported value by ordered distance.
     */
    public static function resolveEffort(EngineEnum $engine, string $model, string $configured): ?string
    {
        $supported = self::find($engine, $model)['efforts'] ?? [];
        if (!$supported) {
            return null;
        }
        if (in_array($configured, $supported, true)) {
            return $configured;
        }
        $configuredIdx = array_search($configured, self::EFFORT_ORDER, true);
        if ($configuredIdx === false) {
            return $supported[0];
        }

        $best = $supported[0];
        $bestDistance = PHP_INT_MAX;
        foreach ($supported as $candidate) {
            $distance = abs(array_search($candidate, self::EFFORT_ORDER, true) - $configuredIdx);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * For the Settings model dropdown.
     */
    public static function forSettings(): array
    {
        $out = [];
        foreach (EngineEnum::cases() as $engine) {
            $out[$engine->value] = collect(self::all($engine))
                ->map(fn ($m, $id) => [
                    'id' => $id,
                    'label' => $m[0],
                    'reasoning' => $m[1] !== [],
                    'efforts' => $m[1],
                    'vision' => $m[2],
                    'deprecated' => $m[3],
                    'description' => $m[4] ?? $m[0],
                ])
                ->values()->all();
        }

        return $out;
    }

    /**
     * EntityEnum::supportedReasoningEfforts() families, for ported ids not in the curated list.
     */
    private static function laddersFor(string $id): array
    {
        return match (true) {
            str_contains($id, '-pro') => self::GPT5_PRO,
            str_starts_with($id, 'gpt-5.5') => self::GPT5_5,
            (bool)preg_match('/^gpt-5\.\d/', $id) => self::GPT5_1_PLUS,
            str_starts_with($id, 'gpt-5') => self::GPT5_ORIGINAL,
            default => self::O_SERIES,
        };
    }

    private static function guessVision(EngineEnum $engine, string $id): bool
    {
        return match ($engine) {
            EngineEnum::OPENAI, EngineEnum::ASSEMBLYAI => (bool)preg_match('/^(gpt-4o|gpt-4\.1|gpt-5|o1$|o3$|o4)/', $id) && !str_contains($id, 'search') && !str_contains($id, 'realtime'),
            EngineEnum::ANTHROPIC => !str_starts_with($id, 'claude-2'),
            EngineEnum::GEMINI => true,
            EngineEnum::XAI => str_contains($id, 'vision') || str_starts_with($id, 'grok-4'),
            EngineEnum::OPENROUTER => (bool)preg_match('~^(openai/gpt-4o|openai/gpt-5|anthropic/claude-3|google/gemini)~', $id),
            default => false,
        };
    }
}
