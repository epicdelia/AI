<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * European Union Regulatory Compliance:
 * 1. European Commission VIES VAT verification & audit token caching.
 * 2. EU-27 Destination VAT & One-Stop Shop (OSS) calculations.
 * 3. EU AI Act (Regulation (EU) 2024/1689 Article 50) machine-readable provenance & human editorial tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->boolean('vies_verified')->default(false);
            $table->string('vies_request_identifier')->nullable();
            $table->timestamp('vies_verified_at')->nullable();
            $table->string('vies_company_name')->nullable();
            $table->text('vies_company_address')->nullable();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('vat_amount', 10, 2)->nullable();
            $table->decimal('gross_amount', 10, 2)->nullable();
            $table->string('oss_country_code', 3)->nullable();
            $table->boolean('is_ai_assisted')->default(false);
            $table->string('ai_model_name', 100)->nullable();
            $table->timestamp('human_reviewed_at')->nullable();
        });

        Schema::table('scripts', function (Blueprint $table) {
            $table->boolean('is_ai_assisted')->default(false);
            $table->string('ai_model_name', 100)->nullable();
            $table->timestamp('human_reviewed_at')->nullable();
            $table->text('editorial_notes')->nullable();
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->boolean('is_ai_assisted')->default(false);
            $table->string('ai_model_name', 100)->nullable();
            $table->timestamp('human_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['is_ai_assisted', 'ai_model_name', 'human_reviewed_at']);
        });

        Schema::table('scripts', function (Blueprint $table) {
            $table->dropColumn(['is_ai_assisted', 'ai_model_name', 'human_reviewed_at', 'editorial_notes']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'vat_amount', 'gross_amount', 'oss_country_code', 'is_ai_assisted', 'ai_model_name', 'human_reviewed_at']);
        });

        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->dropColumn(['vies_verified', 'vies_request_identifier', 'vies_verified_at', 'vies_company_name', 'vies_company_address']);
        });
    }
};
