<?php

namespace App\Domains\Privacy;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Automated Data Retention & Ephemeral Purge Engine.
 * Fulfills GDPR Article 17 (Right to Erasure) by purging raw creative video drafts,
 * scratch proofs, and uploaded files after campaign completion, while preserving
 * statutory commercial invoices and signed contracts.
 */
class DataRetentionPurger
{
    /**
     * Purge temporary files for a single deal.
     */
    public static function purgeDeal(Deal $deal, ?User $by = null): array
    {
        return self::purgeForDeal($deal, $by);
    }

    public static function purgeForDeal(Deal $deal, ?User $by = null): array
    {
        $purgedFilesCount = 0;
        $purgedAnnotationsCount = 0;

        // 1. Delete associated physical files
        foreach ($deal->files as $file) {
            if ($file->path && Storage::disk('local')->exists($file->path)) {
                Storage::disk('local')->delete($file->path);
            }
            $file->delete();
            $purgedFilesCount++;
        }

        // 2. Clear video timecode revision annotations
        $purgedAnnotationsCount = $deal->annotations()->delete();

        // 3. Mark deal as purged
        $deal->update([
            'retention_purged_at' => now(),
            'notes' => trim(($deal->notes ? $deal->notes . "\n" : '') . '[GDPR Art. 17] Ephemeral creative files purged on ' . now()->toIso8601String()),
        ]);

        Audit::log('deal_linked', $by, $deal, [
            'action' => 'data_retention_purged',
            'files_purged' => $purgedFilesCount,
            'annotations_purged' => $purgedAnnotationsCount,
        ]);

        AgentEvent::log('lead', "Ephemeral assets for Deal #{$deal->id} ({$deal->brand_name}) purged under GDPR Article 17.");

        return [
            'ok' => true,
            'deal_id' => $deal->id,
            'brand_name' => $deal->brand_name,
            'purged_files_count' => $purgedFilesCount,
            'purged_annotations_count' => $purgedAnnotationsCount,
            'purged_at' => now()->toIso8601String(),
            'statutory_records_preserved' => true,
            'message' => 'Ephemeral creative files successfully erased. Invoices and e-signed DPA preserved for tax compliance.',
        ];
    }

    /**
     * Run automated retention purge on eligible completed deals.
     */
    public static function runAutomatedRetentionPurge(): int
    {
        $eligibleDeals = Deal::where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->whereNull('retention_purged_at')
            ->where('paid_at', '<=', now()->subDays(60))
            ->get();

        $count = 0;
        foreach ($eligibleDeals as $deal) {
            self::purgeForDeal($deal);
            $count++;
        }

        return $count;
    }
}
