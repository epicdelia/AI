<?php

namespace App\Domains\YouTube;

use App\Models\Deal;
use App\Models\Script;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-publish pre-flight checks (LUFS loudness, Ad Suitability, technical specs, FTC paid promotion).
 */
class VideoCheck extends Model
{
    protected $fillable = [
        'video_upload_id',
        'script_id',
        'file_path',
        'integrated_lufs',
        'true_peak_db',
        'lufs_status',
        'loudness_summary',
        'monetization_rating',
        'monetization_score',
        'flagged_guidelines',
        'resolution',
        'aspect_ratio',
        'fps',
        'has_clipping',
        'has_paid_promotion',
        'deal_id',
        'paid_watermark_synced',
        'technical_notes',
    ];

    protected function casts(): array
    {
        return [
            'integrated_lufs' => 'float',
            'true_peak_db' => 'float',
            'monetization_score' => 'integer',
            'flagged_guidelines' => 'array',
            'has_clipping' => 'boolean',
            'has_paid_promotion' => 'boolean',
            'paid_watermark_synced' => 'boolean',
            'technical_notes' => 'array',
            'fps' => 'float',
        ];
    }

    public function videoUpload(): BelongsTo
    {
        return $this->belongsTo(VideoUpload::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}

