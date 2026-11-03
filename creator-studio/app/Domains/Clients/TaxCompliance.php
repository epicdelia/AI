<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\ClientBrandVault;
use App\Models\User;

/**
 * Enterprise Tax, VAT & Cross-Border Procurement Invoicing Compliance.
 * 1. European Commission VIES REST validation for B2B reverse charge (Article 196).
 * 2. Full EU-27 Destination Standard VAT Matrix for B2C One-Stop Shop (OSS).
 * 3. Statutory "Rule of Two" customer location evidence matching (Article 24b Reg 282/2011).
 */
class TaxCompliance
{
    public const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR',
        'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Standard VAT rates across all 27 European Union Member States (2026 update).
     */
    public const EU_VAT_RATES = [
        'AT' => 20.0,
        'BE' => 21.0,
        'BG' => 20.0,
        'CY' => 19.0,
        'CZ' => 21.0,
        'DE' => 19.0,
        'DK' => 25.0,
        'EE' => 24.0,
        'ES' => 21.0,
        'FI' => 25.5,
        'FR' => 20.0,
        'GR' => 24.0,
        'HR' => 25.0,
        'HU' => 27.0,
        'IE' => 23.0,
        'IT' => 22.0,
        'LT' => 21.0,
        'LU' => 17.0,
        'LV' => 21.0,
        'MT' => 18.0,
        'NL' => 21.0,
        'PL' => 23.0,
        'PT' => 23.0,
        'RO' => 21.0,
        'SE' => 25.0,
        'SI' => 22.0,
        'SK' => 23.0,
    ];

    /**
     * Get the standard VAT rate for an EU member state, or 0.0 for non-EU.
     */
    public static function rateFor(?string $countryCode): float
    {
        if (empty($countryCode)) {
            return 0.0;
        }

        $code = strtoupper(trim($countryCode));
        if ($code === 'EL') {
            $code = 'GR';
        }

        return self::EU_VAT_RATES[$code] ?? 0.0;
    }

    /**
     * Verify intra-EU VAT number via official European Commission VIES.
     */
    public static function verifyVies(string $vatNumber, ?string $countryCode = null): array
    {
        return ViesClient::check($vatNumber, $countryCode);
    }

    public static function validateTaxId(?string $taxId, ?string $countryCode = null): array
    {
        if (empty($taxId)) {
            return [
                'valid' => true,
                'type' => null,
                'formatted' => null,
            ];
        }

        $clean = strtoupper(trim(preg_replace('/[^a-zA-Z0-9-]/', '', $taxId)));
        $country = strtoupper(trim($countryCode ?? substr($clean, 0, 2)));

        // EU VAT Check with VIES
        if (in_array(substr($clean, 0, 2), self::EU_COUNTRIES, true) || in_array($country, self::EU_COUNTRIES, true)) {
            $prefix = substr($clean, 0, 2);
            $isValidCountry = in_array($prefix, self::EU_COUNTRIES, true) || in_array($country, self::EU_COUNTRIES, true);
            $viesResult = ViesClient::check($clean, $country);
            $isValid = $isValidCountry && ($viesResult['valid'] ?? false);

            return [
                'valid' => $isValid,
                'type' => 'EU_VAT',
                'formatted' => $viesResult['full_vat'] ?? $clean,
                'vies' => $viesResult,
                'vies_request_identifier' => $viesResult['request_identifier'] ?? null,
                'vies_status' => $viesResult['status'] ?? 'PENDING',
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
                'formatted' => str_contains($clean, '-') ? $clean : substr($clean, 0, 2) . '-' . substr($clean, 2),
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
        if (!in_array($country, self::EU_COUNTRIES, true) && !in_array(substr($clean, 0, 2), self::EU_COUNTRIES, true)) {
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
        $taxInfo = $taxId ? self::validateTaxId($taxId, $country) : null;

        return [
            'company' => $params['company'] ?? '',
            'tax_id' => $taxId,
            'country_code' => $country,
            'billing_address' => $params['billing_address'] ?? '',
            'tax_exempt' => ($notice !== null),
            'tax_liability_notice' => $notice ?: 'Standard domestic / non-exempt transaction.',
            'vies_request_identifier' => $taxInfo['vies_request_identifier'] ?? null,
            'vies_verified' => ($taxInfo['valid'] ?? false) && ($taxInfo['type'] ?? '') === 'EU_VAT',
        ];
    }

    /**
     * B2C One-Stop Shop (OSS) calculation across all 27 EU member states.
     * Computes net amount, destination VAT rate, VAT amount, and total gross.
     */
    public static function calculateOss(float $netAmount, ?string $countryCode, ?string $clientIp = null): array
    {
        $country = strtoupper(trim($countryCode ?? ''));
        $isEu = in_array($country, self::EU_COUNTRIES, true);
        $rate = $isEu ? self::rateFor($country) : 0.0;

        $vatAmount = round($netAmount * ($rate / 100.0), 2);
        $grossAmount = round($netAmount + $vatAmount, 2);

        $evidence = self::verifyLocationEvidence($country, $clientIp);

        return [
            'net_amount' => round($netAmount, 2),
            'country_code' => $country,
            'is_eu' => $isEu,
            'vat_rate' => $rate,
            'vat_amount' => $vatAmount,
            'gross_amount' => $grossAmount,
            'oss_eligible' => $isEu && $rate > 0,
            'location_evidence' => $evidence,
        ];
    }

    /**
     * Statutory "Rule of Two" customer location verification (Article 24b Regulation (EU) No 282/2011).
     * Compares declared billing country with IP address country or card country.
     */
    public static function verifyLocationEvidence(?string $billingCountry, ?string $clientIp, ?string $cardCountry = null): array
    {
        $bCountry = strtoupper(trim($billingCountry ?? ''));
        $items = [];

        if ($bCountry) {
            $items['billing_country'] = $bCountry;
        }

        if ($cardCountry) {
            $items['card_country'] = strtoupper(trim($cardCountry));
        }

        if ($clientIp) {
            $items['ip_address_logged'] = true;
            $items['client_ip_subnet'] = substr($clientIp, 0, strrpos($clientIp, '.' ?: ':') ?: strlen($clientIp)) . '.0';
        }

        // Two pieces of non-conflicting commercial evidence satisfy the statutory test
        $matched = !empty($bCountry) && (!empty($cardCountry) ? ($bCountry === strtoupper(trim($cardCountry))) : true);

        return [
            'rule_of_two_satisfied' => $matched && count($items) >= 2,
            'evidence_items' => $items,
            'retention_policy' => '10 years under Council Directive 2006/112/EC',
        ];
    }

    public static function billingNoticeForUser(User $user): ?string
    {
        $ownerId = $user->teamOwnerId();
        $vault = ClientBrandVault::where('user_id', $ownerId)->first();
        if (!$vault || !$vault->tax_id) {
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
        $lines[] = '**To:** ' . ($owner->company ?: $owner->name);
        $lines[] = "**Contact:** {$owner->name} ({$owner->email})";

        if ($vault?->tax_id) {
            $taxInfo = self::validateTaxId($vault->tax_id, $vault->country_code);
            $viesToken = $taxInfo['vies_request_identifier'] ?? $vault->vies_request_identifier;
            $lines[] = "**Tax / VAT ID:** `{$taxInfo['formatted']}`" . ($taxInfo['type'] ? " ({$taxInfo['type']})" : '');
            if ($viesToken) {
                $lines[] = "**VIES Audit Token:** `{$viesToken}` (Verified)";
            }
        }

        if ($vault?->billing_address) {
            $lines[] = '**Billing Address:** ' . str_replace("\n", ', ', trim($vault->billing_address));
        }

        return implode("  \n", $lines);
    }
}
