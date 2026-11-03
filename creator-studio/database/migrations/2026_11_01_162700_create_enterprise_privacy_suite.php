<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise High-ROI Privacy Suite:
 * 1. Self-Serve Data Processing Agreement (DPA) execution under GDPR Article 28.
 * 2. Enterprise Zero Data Retention (ZDR) & Confidentiality Certification.
 * 3. Sub-Processor Directory & Notification Subscribers.
 * 4. Immutable Client Data Access Transparency Log (GDPR Article 30 RoPA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->unsignedInteger('retention_days')->default(60);
            $table->boolean('zdr_enabled')->default(true);
            $table->boolean('auto_redact_secrets')->default(true);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('dpa_signed_at')->nullable();
            $table->string('dpa_signer_name')->nullable();
            $table->string('dpa_signer_email')->nullable();
            $table->string('dpa_signature_hash', 64)->nullable();
            $table->boolean('zdr_certified')->default(false);
            $table->string('zdr_certificate_id', 40)->nullable();
            $table->timestamp('retention_purged_at')->nullable();
        });

        Schema::create('sub_processor_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('company_name', 150)->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('privacy_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('actor_name', 100);
            $table->string('action', 50); // read, update, purge, export
            $table->string('resource', 100); // brand_vault, brief, raw_media, dpa
            $table->string('ip_subnet', 45); // e.g. 192.168.1.0 or 2001:db8::
            $table->string('purpose', 255);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_access_logs');
        Schema::dropIfExists('sub_processor_subscribers');

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'dpa_signed_at',
                'dpa_signer_name',
                'dpa_signer_email',
                'dpa_signature_hash',
                'zdr_certified',
                'zdr_certificate_id',
                'retention_purged_at',
            ]);
        });

        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->dropColumn(['retention_days', 'zdr_enabled', 'auto_redact_secrets']);
        });
    }
};
