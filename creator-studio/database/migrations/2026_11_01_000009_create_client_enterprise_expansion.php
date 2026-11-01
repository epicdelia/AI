<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise expansion for client portal:
 * 1. Webhook URL (Slack/Teams/Discord) and tax/procurement billing info on brand vaults.
 * 2. Partner referral code, credits, and attribution on users and deals.
 * 3. Multi-tier proposal selection tracking on deals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->string('slack_webhook_url')->nullable();
            $table->string('tax_id', 50)->nullable(); // EU VAT, UK VAT, US EIN
            $table->text('billing_address')->nullable();
            $table->string('country_code', 3)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 32)->nullable()->unique();
            $table->decimal('referral_credits', 10, 2)->default(0.00);
            $table->foreignId('referred_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->string('selected_tier', 40)->nullable();
            $table->foreignId('referral_partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('referral_credit_earned', 10, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_partner_id');
            $table->dropColumn(['selected_tier', 'referral_credit_earned']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['referral_code', 'referral_credits']);
        });

        Schema::table('client_brand_vaults', function (Blueprint $table) {
            $table->dropColumn(['slack_webhook_url', 'tax_id', 'billing_address', 'country_code']);
        });
    }
};
