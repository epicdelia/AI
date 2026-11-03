<?php

namespace App\Domains\YouTube\PreFlight;

use App\Domains\YouTube\VideoCheck;
use App\Domains\YouTube\VideoUpload;
use App\Models\Script;

/**
 * YouTube Studio Pre-Flight Quality Guard Suite.
 * Orchestrates Broadcast Loudness, Advertiser-Friendly Guidelines, Technical Specs, and FTC Disclosures.
 */
class PreFlightSuite
{
    public function __construct(
        private AudioLoudnessAnalyzer $loudnessAnalyzer,
        private AdSuitabilityShield $adShield
    ) {
    }

    /**
     * Run full pre-flight suite for an upload or draft.
     *
     * @param  array{
     *     video_upload_id?: ?int,
     *     script_id?: ?int,
     *     file_path?: ?string,
     *     title: string,
     *     description?: ?string,
     *     body?: ?string,
     *     deal_id?: ?int,
     *     resolution?: ?string,
     *     aspect_ratio?: ?string,
     *     fps?: ?float
     * }  $params
     */
    public function run(array $params): VideoCheck
    {
        $uploadId = $params['video_upload_id'] ?? null;
        $scriptId = $params['script_id'] ?? null;
        $filePath = $params['file_path'] ?? null;

        if ($uploadId && !$filePath) {
            $upload = VideoUpload::find($uploadId);
            if ($upload) {
                $filePath = $upload->path;
                $params['title'] = $params['title'] ?? $upload->title;
                $params['description'] = $params['description'] ?? $upload->description;
                $scriptId = $scriptId ?? $upload->script_id;
            }
        }

        if ($scriptId && empty($params['body'])) {
            $script = Script::find($scriptId);
            if ($script) {
                $params['body'] = $script->body;
                $params['title'] = $params['title'] ?? $script->title;
            }
        }

        // 1. Audio Loudness Analysis
        $loudness = $this->loudnessAnalyzer->analyze($filePath);

        // 2. Ad Suitability & FTC Compliance
        $adAudit = $this->adShield->audit([
            'title' => $params['title'] ?? '',
            'description' => $params['description'] ?? '',
            'body' => $params['body'] ?? '',
            'deal_id' => $params['deal_id'] ?? null,
        ]);

        // 3. Technical Compliance
        $resolution = $params['resolution'] ?? '3840x2160'; // 4K default for tech exports
        $aspectRatio = $params['aspect_ratio'] ?? '16:9';
        $fps = (float)($params['fps'] ?? 60.0);

        $technicalNotes = [
            'is_uhd_ready' => str_contains($resolution, '3840') || str_contains($resolution, '2160'),
            'is_high_framerate' => $fps >= 50.0,
            'container' => 'MP4 (H.264 / AAC or AV1)',
        ];

        return VideoCheck::updateOrCreate(
            [
                'video_upload_id' => $uploadId,
                'script_id' => $scriptId,
            ],
            [
                'file_path' => $filePath,
                'integrated_lufs' => $loudness['integrated_lufs'],
                'true_peak_db' => $loudness['true_peak_db'],
                'lufs_status' => $loudness['status'],
                'loudness_summary' => $loudness['summary'] . ' ' . $loudness['recommendation'],
                'monetization_rating' => $adAudit['monetization_rating'],
                'monetization_score' => $adAudit['monetization_score'],
                'flagged_guidelines' => $adAudit['flagged_guidelines'],
                'resolution' => $resolution,
                'aspect_ratio' => $aspectRatio,
                'fps' => $fps,
                'has_clipping' => $loudness['has_clipping'],
                'has_paid_promotion' => $adAudit['has_paid_promotion'],
                'deal_id' => $params['deal_id'] ?? null,
                'paid_watermark_synced' => $adAudit['paid_watermark_synced'],
                'technical_notes' => $technicalNotes,
            ]
        );
    }
}
