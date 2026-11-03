<?php

namespace App\Domains\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * European Union AI Act (Regulation (EU) 2024/1689) Article 50 Compliance Suite.
 * Enforces transparency, machine-readable detectability headers, synthetic media watermarking,
 * and human-in-the-loop editorial accountability.
 */
class AiTransparency
{
    public const REGULATION = 'Regulation (EU) 2024/1689 (EU AI Act)';
    public const ARTICLE = 'Article 50';
    public const SYSTEM_NAME = 'Delia Creator Studio AI Engine';

    /**
     * Standard machine-readable HTTP headers required for Article 50 detectability.
     */
    public static function headers(string $model = 'default', bool $humanReviewed = true): array
    {
        return [
            'X-AI-Generated' => 'true',
            'X-AI-System' => self::SYSTEM_NAME,
            'X-AI-Regulation' => 'Regulation-(EU)-2024/1689-Article-50',
            'X-AI-Editorial-Status' => $humanReviewed ? 'human-reviewed' : 'synthetic-draft',
            'X-AI-Model' => $model,
        ];
    }

    /**
     * Attach Article 50 machine-readable headers to an HTTP response.
     */
    public static function markResponse(SymfonyResponse $response, string $model = 'default', bool $humanReviewed = true): SymfonyResponse
    {
        foreach (self::headers($model, $humanReviewed) as $k => $v) {
            $response->headers->set($k, $v);
        }

        return $response;
    }

    /**
     * Machine-readable provenance manifest (Schema.org / C2PA inspired).
     */
    public static function manifest(string $title, string $type = 'script', ?string $model = null, bool $humanReviewed = true): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $title,
            'learningResourceType' => $type,
            'creator' => [
                '@type' => 'Person',
                'name' => 'Delia Lazarescu',
                'alternateName' => 'Tech.Unicorn',
                'url' => url('/'),
            ],
            'producer' => [
                '@type' => 'SoftwareApplication',
                'name' => self::SYSTEM_NAME,
                'applicationCategory' => 'GenerativeAI',
                'softwareVersion' => '2026.11',
            ],
            'aiTransparency' => [
                'regulation' => self::REGULATION,
                'compliance_article' => self::ARTICLE,
                'is_ai_generated' => true,
                'model_name' => $model ?: 'gpt-4o',
                'human_editorial_review' => $humanReviewed,
                'editorial_responsibility' => 'Natural Person (Delia Lazarescu)',
                'timestamp' => now()->toIso8601String(),
            ],
            'creditText' => $humanReviewed
                ? 'Drafted with assistive generative AI; inspected, edited, and approved by Delia Lazarescu in compliance with EU AI Act Article 50(4).'
                : 'Synthetic AI-generated draft awaiting human editorial sign-off pursuant to EU AI Act Article 50.',
        ];
    }

    /**
     * Embed machine-readable metadata and human transparency footnote into markdown or text.
     */
    public static function embedWatermark(string $content, ?string $model = null, bool $humanReviewed = true, bool $includeFootnote = true): string
    {
        $metaComment = sprintf(
            "<!-- eu-ai-act-disclosure: %s -->\n\n",
            json_encode([
                'regulation' => self::REGULATION,
                'article' => self::ARTICLE,
                'system' => self::SYSTEM_NAME,
                'model' => $model ?: 'creator-studio-ai',
                'human_reviewed' => $humanReviewed,
                'timestamp' => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES)
        );

        $footnote = '';
        if ($includeFootnote) {
            $statusText = $humanReviewed
                ? 'human-reviewed and verified by creator Delia (@Tech.Unicorn)'
                : 'synthetic draft awaiting final human review';

            $footnote = "\n\n---\n\n> 🤖 **EU AI Act Transparency Disclosure (Article 50):** This content was generated with assistive AI technology and is {$statusText}.";
        }

        return $metaComment . trim($content) . $footnote;
    }

    /**
     * Human-in-the-loop editorial sign-off for models (Script, SocialPost, Deal).
     */
    public static function certifyHumanReview(Model $model, ?User $reviewer = null, ?string $notes = null): Model
    {
        $model->update([
            'is_ai_assisted' => true,
            'human_reviewed_at' => now(),
            'editorial_notes' => $notes ?: 'Inspected and certified in accordance with EU AI Act Article 50(4) editorial standards.',
        ]);

        return $model->fresh();
    }

    /**
     * Check if a deliverable or text contains statutory AI disclosure.
     */
    public static function hasDisclosure(string $text): bool
    {
        $lower = strtolower($text);

        return str_contains($lower, 'ai act')
            || str_contains($lower, 'eu-ai-act-disclosure')
            || str_contains($lower, 'generated with ai')
            || str_contains($lower, 'artificial intelligence')
            || str_contains($lower, 'ai-assisted');
    }
}
