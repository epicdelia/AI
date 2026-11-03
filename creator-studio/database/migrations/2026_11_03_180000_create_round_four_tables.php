<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round four: income beyond sponsorships and less back-and-forth.
 * - Digital products (one-off sales, expiring download links).
 * - Affiliate links (/go/{slug}, clicks per day, commissions she logs, dead-link check).
 * - Newsletter campaigns to her subscribers (batched, one-click unsubscribe).
 * - Translated titles/descriptions pushed to YouTube.
 * - Inquiry autopilot (fit rules, instant reply) and client approval of sponsored copy.
 * - Stage checklists on team tasks, weekly capacity per person.
 * - Community: questions → ideas → "your question became a video", member referrals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedTinyInteger('pro_discount_percent')->default(0);
            $table->string('status', 20)->default('draft'); // draft, published
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('status', 20)->default('pending'); // pending, paid, refunded
            $table->string('download_token_hash', 64)->nullable()->unique();
            $table->unsignedTinyInteger('downloads')->default(0);
            $table->unsignedTinyInteger('max_downloads')->default(5);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('url', 1000);
            $table->string('partner', 120)->nullable();
            $table->string('commission_note', 200)->nullable();
            $table->string('placement', 40)->nullable(); // video, post, newsletter, bio, other
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->boolean('broken')->default(false);
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('affiliate_links')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['link_id', 'day']);
        });

        Schema::create('affiliate_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->nullable()->constrained('affiliate_links')->nullOnDelete();
            $table->string('partner', 120)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('earned_on');
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 200);
            $table->longText('body');                    // Markdown
            $table->json('segment')->nullable();         // {source?, magnet?}
            $table->string('status', 20)->default('draft'); // draft, sending, sent
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 200)->nullable();
            $table->unique(['campaign_id', 'subscriber_id']);
        });

        Schema::create('video_localizations', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 20);
            $table->string('lang', 10);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->unique(['video_id', 'lang']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->string('inquiry_budget', 60)->nullable();
            $table->string('inquiry_timeline', 60)->nullable();
            $table->string('inquiry_product_url', 500)->nullable();
            $table->text('inquiry_deliverables')->nullable();
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->string('fit_verdict', 20)->nullable();    // pursue, review, pass
            $table->json('fit_reasons')->nullable();
            $table->string('auto_reply', 20)->nullable();     // sent, draft
            $table->text('auto_reply_body')->nullable();
            $table->text('copy_text')->nullable();            // sponsored copy sent for approval
            $table->string('copy_status', 20)->nullable();    // pending, approved, changes
            $table->text('copy_note')->nullable();
            $table->timestamp('copy_sent_at')->nullable();
            $table->timestamp('copy_approved_at')->nullable();
            $table->date('copy_due_on')->nullable();
            $table->unsignedTinyInteger('copy_reminders')->default(0);
            $table->boolean('copy_override')->default(false);
        });

        Schema::table('team_tasks', function (Blueprint $table) {
            $table->json('checklist')->nullable();          // {item_key: done_at}
            $table->unsignedSmallInteger('estimate_minutes')->nullable();
        });

        Schema::table('team_rates', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_hours')->default(40);
        });

        Schema::table('community_items', function (Blueprint $table) {
            $table->foreignId('idea_id')->nullable()->constrained('ideas')->nullOnDelete();
            $table->timestamp('video_notified_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('member_ref_code', 16)->nullable()->unique();
        });

        Schema::create('member_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('pro_rewarded_at')->nullable();
            $table->timestamps();
            $table->index(['referrer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_referrals');
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['member_ref_code']); // SQLite can't drop a column that has an index
            $t->dropColumn('member_ref_code');
        });
        Schema::table('community_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('idea_id');
            $t->dropColumn('video_notified_at');
        });
        Schema::table('team_rates', fn (Blueprint $t) => $t->dropColumn('weekly_hours'));
        Schema::table('team_tasks', fn (Blueprint $t) => $t->dropColumn(['checklist', 'estimate_minutes']));
        Schema::table('deals', fn (Blueprint $t) => $t->dropColumn(['inquiry_budget', 'inquiry_timeline', 'inquiry_product_url', 'inquiry_deliverables',
            'fit_score', 'fit_verdict', 'fit_reasons', 'auto_reply', 'auto_reply_body', 'copy_text', 'copy_status', 'copy_note', 'copy_sent_at',
            'copy_approved_at', 'copy_due_on', 'copy_reminders', 'copy_override']));
        foreach (['video_localizations', 'campaign_sends', 'campaigns', 'affiliate_earnings', 'affiliate_clicks', 'affiliate_links', 'orders', 'products'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
