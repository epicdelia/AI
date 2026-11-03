<?php

namespace App\Domains\Usage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One AI call: which feature asked, which engine/model answered, tokens and time.
 */
class AiUsage extends Model
{
    protected $table = 'ai_usages';

    protected $fillable = ['feature', 'engine', 'model', 'unit', 'quantity', 'input_tokens', 'output_tokens', 'duration_ms', 'ok', 'error'];

    /**
     * Totals for the Settings usage card.
     */
    public static function summary(int $days = 30): array
    {
        $since = now()->subDays($days);
        $base = self::query()->where('created_at', '>=', $since);

        $totals = (clone $base)->selectRaw('count(*) as calls, sum(case when ok then 0 else 1 end) as failed,
            coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens,
            coalesce(avg(duration_ms), 0) as avg_ms')->first();

        $byFeature = (clone $base)->select(
            'feature',
            'unit',
            DB::raw('count(*) as calls'),
            DB::raw('coalesce(sum(quantity), 0) as quantity'),
            DB::raw("coalesce(sum(case when unit = 'token' then quantity else 0 end), 0) as tokens")
        )
            ->groupBy('feature', 'unit')->orderByDesc('calls')->get();

        $byDay = (clone $base)->select(
            DB::raw('date(created_at) as day'),
            DB::raw('count(*) as calls'),
            DB::raw('coalesce(sum(input_tokens), 0) + coalesce(sum(output_tokens), 0) as tokens')
        )
            ->groupBy('day')->orderBy('day')->get();

        return [
            'days' => $days,
            'calls' => (int)$totals->calls,
            'failed' => (int)$totals->failed,
            'input_tokens' => (int)$totals->input_tokens,
            'output_tokens' => (int)$totals->output_tokens,
            'avg_ms' => (int)round((float)$totals->avg_ms),
            'by_feature' => $byFeature,
            'by_day' => $byDay,
            'recent' => self::query()->latest()->take(15)->get(),
            'limits' => UsageLimit::summary(),
        ];
    }

    protected static function booted(): void
    {
        // Token rows: quantity is input + output (MagicAI's HasWords::calculate equivalent).
        static::creating(function (self $u) {
            $u->unit ??= 'token';
            if ($u->unit === 'token' && $u->quantity === null && ($u->input_tokens !== null || $u->output_tokens !== null)) {
                $u->quantity = (int)$u->input_tokens + (int)$u->output_tokens;
            }
            if ($u->unit !== 'token' && $u->quantity === null) {
                $u->quantity = 1;
            }
        });

        // Credit system tracking: record per-user ledger and deduct if not BYOK.
        static::created(function (self $u) {
            try {
                $user = auth()->user();
                if (!$user) {
                    return;
                }
                $engine = \App\Domains\Engine\Enums\EngineEnum::tryFrom((string)$u->engine);
                $hasByok = $engine ? $user->hasApiKeyFor($engine) : false;
                $quantity = (int)($u->quantity ?? 1);
                $ratePerUnit = match ($u->unit) {
                    'token' => 0.000002,
                    'image' => 0.040000,
                    'second' => 0.002000,
                    default => 0.000100,
                };
                $cost = $hasByok ? 0.0 : round($quantity * $ratePerUnit, 4);

                \App\Models\UserUsageCredit::create([
                    'user_id' => $user->id,
                    'model_key' => (string)($u->model ?? 'default'),
                    'feature' => (string)($u->feature ?? 'studio'),
                    'unit' => (string)($u->unit ?? 'token'),
                    'quantity' => $quantity,
                    'unit_price' => $ratePerUnit,
                    'total_cost' => $cost,
                    'byok' => $hasByok,
                ]);

                if (!$hasByok && $cost > 0) {
                    $user->deductCredits($cost);
                }
            } catch (\Throwable $e) {
                // Silently ignore if credit tables are not present or during CLI runs
            }
        });
    }

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
        ];
    }
}
