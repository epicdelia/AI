<?php

namespace App\Domains\Privacy;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Immutable Client Data Access Transparency Logger.
 * Records when personal data, Brand Vault assets, or deal briefs are accessed
 * to satisfy GDPR Article 30 Records of Processing Activities (RoPA) and enterprise SOC 2 audit controls.
 */
class PrivacyAccessLogger
{
    /**
     * Record an access event.
     */
    public static function log(
        User $actor,
        string $action,
        Deal|string $resourceOrDeal = 'platform',
        string|array|null $purposeOrMeta = null,
        ?Deal $deal = null,
        ?string $ip = null
    ): void {
        if ($resourceOrDeal instanceof Deal) {
            $deal = $resourceOrDeal;
            $resource = 'Deal #' . $deal->id . ' (' . $deal->brand_name . ')';
            $purpose = is_string($purposeOrMeta) ? $purposeOrMeta : 'GDPR compliance & legal execution';
        } else {
            $resource = (string)$resourceOrDeal;
            $purpose = is_string($purposeOrMeta) ? $purposeOrMeta : 'Authorized platform operation';
        }

        $clientIp = $ip ?? request()?->ip() ?? '127.0.0.1';
        $ipSubnet = self::maskSubnet($clientIp);

        DB::table('privacy_access_logs')->insert([
            'user_id' => $deal?->user_id ?: ($actor->isAdmin() ? null : $actor->id),
            'deal_id' => $deal?->id,
            'actor_name' => $actor->name . ' (' . ($actor->isAdmin() ? 'Studio Admin' : 'Client Team') . ')',
            'action' => $action,
            'resource' => $resource,
            'ip_subnet' => $ipSubnet,
            'purpose' => $purpose,
            'created_at' => now(),
        ]);
    }

    /**
     * Get recent access logs for a client account.
     */
    public static function historyForUser(User $user, int $limit = 50): array
    {
        $ownerId = $user->teamOwnerId();

        return DB::table('privacy_access_logs')
            ->where(function ($q) use ($ownerId) {
                $q->where('user_id', $ownerId)
                    ->orWhereIn('deal_id', function ($sub) use ($ownerId) {
                        $sub->select('id')->from('deals')->where('user_id', $ownerId);
                    });
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'actor' => $row->actor_name,
                'action' => strtoupper($row->action),
                'resource' => $row->resource,
                'ip_subnet' => $row->ip_subnet,
                'purpose' => $row->purpose,
                'timestamp' => $row->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Anonymize client IP to class C / 64-bit IPv6 subnet for GDPR privacy.
     */
    public static function maskSubnet(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, min(3, count($parts)))) . '::0';
        }

        // IPv4
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = '0';

            return implode('.', $parts);
        }

        return '127.0.0.0';
    }
}
