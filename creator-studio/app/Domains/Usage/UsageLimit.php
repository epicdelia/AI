<?php

namespace App\Domains\Usage;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Monthly limits she sets herself, ported from MagicAI's Entity HasCreditLimit
 * (hasCreditBalance / redirectIfNoCreditBalance / getCreditBalance), minus the plans and
 * shared team pools. Text models are limited by tokens and images by count, measured from
 * ai_usages for the current calendar month in her timezone. An empty limit means no limit.
 *
 * Money is only estimated when she enters her own prices: provider prices change and
 * MagicAI's unitPrice() values are resale credit rates, not API costs.
 */
class UsageLimit
{
    public const UNITS = [
        'token' => 'monthly_token_limit',
        'image' => 'monthly_image_limit',
        'character' => 'monthly_tts_char_limit',
        'video' => 'monthly_video_limit',
    ];

    public static function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::now(Setting::get('timezone', 'Europe/London') ?: 'UTC')->startOfMonth()->utc();
    }

    public static function used(string $unit): int
    {
        return (int)AiUsage::where('unit', $unit)->where('created_at', '>=', self::monthStart())->sum('quantity');
    }

    public static function limit(string $unit): ?int
    {
        $key = self::UNITS[$unit] ?? null;
        $v = $key ? trim((string)Setting::get($key, '')) : '';

        return $v !== '' && ctype_digit($v) && (int)$v > 0 ? (int)$v : null;
    }

    /**
     * MagicAI's hasCreditBalance().
     */
    public static function hasBalance(string $unit, int $needed = 0): bool
    {
        $limit = self::limit($unit);

        return $limit === null || self::used($unit) + $needed <= $limit;
    }

    /**
     * MagicAI's redirectIfNoCreditBalance(), as an exception the API turns into a message.
     */
    public static function ensure(string $unit, int $needed = 0): void
    {
        if (!self::hasBalance($unit, $needed)) {
            $label = [
                'token' => 'AI tokens',
                'image' => 'thumbnail mockups',
                'character' => 'read-aloud characters',
                'video' => 'b-roll clips',
            ][$unit] ?? $unit;

            throw new RuntimeException(sprintf(
                "You've reached this month's limit for %s (%s of %s). Raise it in Settings → Usage limits, or wait until the 1st.",
                $label,
                number_format(self::used($unit)),
                number_format((int)self::limit($unit))
            ));
        }
    }

    /**
     * Cost of one call from her own prices, or null when she hasn't entered any.
     */
    public static function cost(int $inputTokens, int $outputTokens): ?float
    {
        $in = (float)Setting::get('price_input_per_million', '0');
        $out = (float)Setting::get('price_output_per_million', '0');
        if ($in <= 0 && $out <= 0) {
            return null;
        }

        return round(($inputTokens * $in + $outputTokens * $out) / 1_000_000, 4);
    }

    /**
     * Her own prices (per 1M input / output tokens) → this month's estimate, or null.
     */
    public static function estimatedSpend(): ?float
    {
        $in = (float)Setting::get('price_input_per_million', '0');
        $out = (float)Setting::get('price_output_per_million', '0');
        if ($in <= 0 && $out <= 0) {
            return null;
        }
        $row = AiUsage::where('unit', 'token')->where('created_at', '>=', self::monthStart())
            ->selectRaw('coalesce(sum(input_tokens), 0) as i, coalesce(sum(output_tokens), 0) as o')->first();

        return round(($row->i * $in + $row->o * $out) / 1_000_000, 2);
    }

    public static function summary(): array
    {
        $out = [];
        foreach (array_keys(self::UNITS) as $unit) {
            $out[$unit] = [
                'used' => self::used($unit),
                'limit' => self::limit($unit),
            ];
        }

        return [
            'month_start' => self::monthStart()->toIso8601String(),
            'units' => $out,
            'estimated_spend' => self::estimatedSpend(),
        ];
    }
}
