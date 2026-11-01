<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * High-ROI Client Expansion & Security Hardening:
 * 1. Deliverable annotations for collaborative timestamped/frame-by-frame proofing & revision review.
 * 2. Client brand vault for persistent corporate assets, guidelines, and UTM tracking.
 * 3. E-signature verification metadata and add-ons on deals for tamper-evident contracts and upsells.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('signature_hash', 64)->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->string('signer_user_agent')->nullable();
            $table->string('signer_email')->nullable();
            $table->json('add_ons')->nullable();
        });

        Schema::create('deliverable_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('deliverable_index')->default(0);
            $table->string('kind', 30)->default('general'); // video, image, slide, document, general
            $table->decimal('timecode_seconds', 8, 2)->nullable();
            $table->string('position_xy', 50)->nullable(); // e.g. "45.2,60.8" for canvas/slide pins
            $table->unsignedSmallInteger('page_or_slide')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('open'); // open, resolved
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['deal_id', 'deliverable_index', 'status']);
        });

        Schema::create('client_brand_vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('brand_colors')->nullable();
            $table->text('guidelines_markdown')->nullable();
            $table->json('talking_points')->nullable();
            $table->json('dos_and_donts')->nullable();
            $table->json('utm_params')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_brand_vaults');
        Schema::dropIfExists('deliverable_annotations');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['signature_hash', 'signer_ip', 'signer_user_agent', 'signer_email', 'add_ons']);
        });
    }
};
