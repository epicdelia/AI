<?php

namespace App\Domains\Privacy;

/**
 * Enterprise PII, Secret & Credential Redaction Gateway.
 * Scans briefs, comments, and uploaded text payloads to mask leaked
 * API keys, private tokens, passwords, and personal identifiers before storage or LLM processing.
 */
class PiiRedactor
{
    public const SECRET_PATTERNS = [
        'STRIPE_LIVE_KEY' => '/(sk_live_|rk_live_)[0-9a-zA-Z]{20,}/',
        'OPENAI_API_KEY' => '/sk-[a-zA-Z0-9_\-]{24,}/',
        'ANTHROPIC_API_KEY' => '/sk-ant-[a-zA-Z0-9_\-]{24,}/',
        'GITHUB_TOKEN' => '/(ghp_|gho_|ghu_|ghs_|ghr_)[a-zA-Z0-9]{30,}/',
        'AWS_ACCESS_KEY' => '/AKIA[0-9A-Z]{16}/',
        'SLACK_TOKEN' => '/xox[baprs]-[0-9a-zA-Z\-]{20,}/',
        'JWT_BEARER_TOKEN' => '/eyJ[a-zA-Z0-9_\-]{15,}\.eyJ[a-zA-Z0-9_\-]{15,}\.[a-zA-Z0-9_\-]{15,}/',
        'PRIVATE_KEY_HEADER' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    ];

    /**
     * Scan and mask all high-risk credentials and private tokens.
     */
    public static function redact(string $text): array
    {
        if (trim($text) === '') {
            return [
                'sanitized' => $text,
                'redacted_count' => 0,
                'found_types' => [],
            ];
        }

        $sanitized = $text;
        $count = 0;
        $foundTypes = [];

        foreach (self::SECRET_PATTERNS as $type => $regex) {
            $matches = [];
            if (preg_match_all($regex, $sanitized, $matches)) {
                $uniqueMatches = array_unique($matches[0]);
                foreach ($uniqueMatches as $match) {
                    $prefix = substr($match, 0, min(7, strlen($match)));
                    $mask = $prefix.str_repeat('*', max(6, strlen($match) - strlen($prefix)));
                    $sanitized = str_replace($match, "[REDACTED_{$type}:{$mask}]", $sanitized);
                    $count++;
                    $foundTypes[] = $type;
                }
            }
        }

        return [
            'sanitized' => $sanitized,
            'redacted_count' => $count,
            'found_types' => array_values(array_unique($foundTypes)),
        ];
    }

    /**
     * Check if text contains high-risk credentials.
     */
    public static function hasSecrets(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        foreach (self::SECRET_PATTERNS as $regex) {
            if (preg_match($regex, $text)) {
                return true;
            }
        }

        return false;
    }
}

