<?php

namespace App\Domains\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GDPR Article 28 Sub-Processor Transparency Registry & Change Notification Feed.
 */
class SubProcessorRegistry
{
    public const SUBPROCESSORS = [
        [
            'name' => 'Stripe Payments Europe, Ltd. & Stripe, Inc.',
            'purpose' => 'Payment processing, SEPA transfers, credit card tokenization, and tax compliance.',
            'entity_location' => 'Ireland / United States',
            'data_transferred' => 'Billing contact details, corporate address, payment tokens, transaction amounts.',
            'safeguard' => 'EU Standard Contractual Clauses (SCCs) & EU-U.S. Data Privacy Framework (DPF)',
            'certifications' => 'PCI-DSS Level 1, SOC 1, SOC 2 Type II, ISO 27001',
        ],
        [
            'name' => 'OpenAI, L.L.C.',
            'purpose' => 'Enterprise in-memory script generation, ideation assistance, and grammar review.',
            'entity_location' => 'United States',
            'data_transferred' => 'Transient prompt text, creative hooks, script draft outlines (zero training terms).',
            'safeguard' => 'EU Standard Contractual Clauses (SCCs) & Zero Data Retention (ZDR) Enterprise Agreement',
            'certifications' => 'SOC 2 Type II, ISO 27001',
        ],
        [
            'name' => 'Anthropic, PBC',
            'purpose' => 'Contextual reasoning, code snippet analysis, and long-form outline structuring.',
            'entity_location' => 'United States',
            'data_transferred' => 'Creative outline text, code snippets (commercial non-training agreement).',
            'safeguard' => 'EU Standard Contractual Clauses (SCCs)',
            'certifications' => 'SOC 2 Type II, HIPAA Compliant Infrastructure',
        ],
        [
            'name' => 'AssemblyAI, Inc.',
            'purpose' => 'Speech-to-text audio transcript generation for rough video edits.',
            'entity_location' => 'United States',
            'data_transferred' => 'Raw spoken audio recordings and generated text transcripts.',
            'safeguard' => 'EU Standard Contractual Clauses (SCCs)',
            'certifications' => 'SOC 2 Type II',
        ],
        [
            'name' => 'Cloudflare, Inc.',
            'purpose' => 'Edge Content Delivery Network (CDN), DDoS mitigation, and TLS 1.3 encryption.',
            'entity_location' => 'United States / European Union',
            'data_transferred' => 'Encrypted HTTP traffic, client IP subnet addresses (anonymized).',
            'safeguard' => 'EU-U.S. Data Privacy Framework (DPF) & Binding Corporate Rules',
            'certifications' => 'SOC 2 Type II, ISO 27001, PCI-DSS',
        ],
    ];

    public static function all(): array
    {
        return self::SUBPROCESSORS;
    }

    /**
     * Subscribe an enterprise compliance email to 30-day advance sub-processor change notifications.
     */
    public static function subscribe(string $email, ?string $company = null): array
    {
        $cleanEmail = strtolower(trim($email));

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Please provide a valid corporate email address.',
            ]);
        }

        DB::table('sub_processor_subscribers')->updateOrInsert(
            [
                'email' => $cleanEmail,
            ],
            [
                'company_name' => $company ? trim($company) : null,
                'subscribed_at' => now(),
                'updated_at' => now(),
            ]
        );

        return [
            'ok' => true,
            'email' => $cleanEmail,
            'message' => 'Subscribed to 30-day advance sub-processor change notifications.',
        ];
    }

    public static function markdown(): string
    {
        $md = "# Authorized Sub-Processors Directory (GDPR Article 28)\n\n";
        $md .= "Pursuant to Article 28 of Regulation (EU) 2016/679 (GDPR), the following third-party infrastructure providers are authorized to process data on behalf of Delia Creator Studio:\n\n";
        $md .= "| Sub-Processor | Purpose & Scope | Location | Transfer Safeguard | Certifications |\n";
        $md .= "|---|---|---|---|---|\n";

        foreach (self::SUBPROCESSORS as $sub) {
            $md .= sprintf(
                "| **%s** | %s | %s | %s | %s |\n",
                str_replace('|', '/', $sub['name']),
                str_replace('|', '/', $sub['purpose']),
                str_replace('|', '/', $sub['entity_location']),
                str_replace('|', '/', $sub['safeguard']),
                str_replace('|', '/', $sub['certifications'])
            );
        }

        $md .= "\n\n*Notice of Changes:* Subscribed enterprise clients receive notice at least 30 days prior to engaging any new sub-processor, with statutory right to object under Section 4 of our standard DPA.\n";

        return $md;
    }
}
