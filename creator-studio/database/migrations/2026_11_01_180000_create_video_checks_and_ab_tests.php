<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube Studio Parity - Phase 1:
 * 1. video_checks: Pre-publish pre-flight checks (LUFS loudness, Ad Suitability, technical specs, FTC paid promotion).
 * 2. video_ab_tests: Simultaneous Thumbnail + Title Bayesian A/B/C Multi-Variant Testing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_upload_id')->nullable()->constrained('video_uploads')->nullOnDelete();
            $table->foreignId('script_id')->nullable()->constrained('scripts')->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->decimal('integrated_lufs', 5, 2)->default(-14.0);
            $table->decimal('true_peak_db', 5, 2)->default(-1.0);
            $table->string('lufs_status')->default('pass'); // pass, warning, fail
            $table->text('loudness_summary')->nullable();
            $table->string('monetization_rating')->default('green'); // green, yellow, red
            $table->unsignedSmallInteger('monetization_score')->default(100);
            $table->json('flagged_guidelines')->nullable();
            $table->string('resolution')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->decimal('fps', 5, 2)->nullable();
            $table->boolean('has_clipping')->default(false);
            $table->boolean('has_paid_promotion')->default(false);
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->boolean('paid_watermark_synced')->default(false);
            $table->json('technical_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('video_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->string('status')->default('running'); // draft, running, concluded, stopped
            $table->string('original_title');
            $table->unsignedBigInteger('original_thumbnail_media_id')->nullable();
            $table->json('variants'); // array of [{id, title, thumbnail_media_id, thumbnail_url, impressions, clicks, watch_time_sec}]
            $table->unsignedSmallInteger('winner_index')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->json('statistical_summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_ab_tests');
        Schema::dropIfExists('video_checks');
    }
};

