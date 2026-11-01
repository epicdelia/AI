<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\ClientBrandVault;
use App\Models\User;

/**
 * Enterprise Tax, VAT & Cross-Border Procurement Invoicing Compliance.
 * Validates corporate Tax/VAT IDs and automatically applies statutory
 * reverse-charge notices (EU Article 196 / UK VAT Act).
 */
class TaxCompliance
{
    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR',
        'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    public static function validateTaxId(?string $taxId, ?string $countryCode = null): array
    {
        if (empty($taxId)) {
            return ['valid' => true, 'type' => null, 'formatted' => null];
        }

        $clean = strtoupper(trim(preg_replace('/[^a-zA-Z0-9-]/', '', $taxId)));
        $country = strtoupper(trim($countryCode ?? substr($clean, 0, 2)));

        // EU VAT Check
        if (in_array(substr($clean, 0, 2), self::EU_COUNTRIES, true) || in_array($country, self::EU_COUNTRIES, true)) {
            $prefix = substr($clean, 0, 2);
            $isValid = in_array($prefix, self::EU_COUNTRIES, true) && strlen($clean) >= 8 && strlen($clean) <= 15;
            return [
                'valid' => $isValid,
                'type' => 'EU_VAT',
                'formatted' => $clean,
                'notice' => 'Reverse charge: VAT to be accounted for by the recipient pursuant to Article 196 of Council Directive 2006/112/EC.',
            ];
        }

        // UK VAT Check
        if (str_starts_with($clean, 'GB') || $country === 'GB') {
            $isValid = preg_match('/^GB[0-9]{9,12}$/', $clean) === 1;
            return [
                'valid' => $isValid,
                'type' => 'UK_VAT',
                'formatted' => $clean,
                'notice' => 'Reverse charge: Customer to account for VAT to HMRC pursuant to Section 8 of the VAT Act 1994.',
            ];
        }

        // US EIN Check
        if (preg_match('/^[0-9]{2}-?[0-9]{7}$/', $clean)) {
            return [
                'valid' => true,
                'type' => 'US_EIN',
                'formatted' => str_contains($clean, '-') ? $clean : substr($clean, 0, 2).'-'.substr($clean, 2),
                'notice' => 'Services performed by non-US creator outside the United States. Form W-8BEN-E on file.',
            ];
        }

        return [
            'valid' => true,
            'type' => 'CORPORATE_TAX_ID',
            'formatted' => $clean,
            'notice' => null,
        ];
    }

    public static function isValidVat(?string $taxId, ?string $countryCode = null): bool
    {
        if (empty($taxId)) {
            return false;
        }
        $info = self::validateTaxId($taxId, $countryCode);
        return ($info['valid'] ?? false) && ($info['type'] ?? '') === 'EU_VAT';
    }

    public static function reverseChargeNotice(?string $countryCode, ?string $taxId): ?string
    {
        if (empty($taxId)) {
            return null;
        }
        $clean = strtoupper(trim($taxId));
        $country = strtoupper(trim($countryCode ?? substr($clean, 0, 2)));
        if (! in_array($country, self::EU_COUNTRIES, true) && ! in_array(substr($clean, 0, 2), self::EU_COUNTRIES, true)) {
            return null;
        }
        $info = self::validateTaxId($taxId, $country);
        return $info['notice'] ?? null;
    }

    public static function commercialBillingHeader(array $params): array
    {
        $country = $params['country_code'] ?? null;
        $taxId = $params['tax_id'] ?? null;
        $notice = self::reverseChargeNotice($country, $taxId);

        return [
            'company' => $params['company'] ?? '',
            'tax_id' => $taxId,
            'country_code' => $country,
            'billing_address' => $params['billing_address'] ?? '',
            'tax_exempt' => ($notice !== null),
            'tax_liability_notice' => $notice ?: 'Standard domestic / non-exempt transaction.',
        ];
    }

    public static function billingNoticeForUser(User $user): ?string
    {
        $ownerId = $user->teamOwnerId();
        $vault = ClientBrandVault::where('user_id', $ownerId)->first();
        if (! $vault || ! $vault->tax_id) {
            return null;
        }

        $res = self::validateTaxId($vault->tax_id, $vault->country_code);
        return $res['notice'] ?? null;
    }

    public static function billingHeaderForUser(User $user): string
    {
        $owner = User::find($user->teamOwnerId()) ?? $user;
        $vault = ClientBrandVault::where('user_id', $owner->id)->first();

        $lines = [];
        $lines[] = "**To:** ".($owner->company ?: $owner->name);
        $lines[] = "**Contact:** {$owner->name} ({$owner->email})";

        if ($vault?->tax_id) {
            $taxInfo = self::validateTaxId($vault->tax_id, $vault->country_code);
            $lines[] = "**Tax / VAT ID:** `{$taxInfo['formatted']}`".($taxInfo['type'] ? " ({$taxInfo['type']})" : '');
        }

        if ($vault?->billing_address) {
            $lines[] = "**Billing Address:** ".str_replace("\n", ', ', trim($vault->billing_address));
        }

        return implode("  \n", $lines);
    }
}
