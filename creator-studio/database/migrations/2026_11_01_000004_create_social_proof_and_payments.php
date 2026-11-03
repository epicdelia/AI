<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Social proof and recurring money: testimonials collected through a private link and shown
     * on the landing page once she approves them; the email list from the landing page's opt-in;
     * Stripe payments (so retainer renewals count); and inbox leads linked to the deal they became.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 40)->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->string('company')->nullable();
            $table->text('quote')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->boolean('permission')->default(false); // may be shown publicly
            $table->string('status')->default('requested'); // requested, submitted, approved, hidden
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('source', 40)->default('direct');
            $table->json('attribution')->nullable();
            $table->string('magnet')->nullable(); // what they signed up for
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('kind', 20); // checkout, renewal
            $table->string('stripe_id')->nullable()->unique();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->index('paid_at');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable();
            $table->string('subscription_status', 20)->nullable(); // active, cancelled
            $table->index('stripe_subscription_id');
        });

        Schema::table('engagement_items', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('engagement_items', fn (Blueprint $table) => $table->dropColumn('deal_id'));
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropColumn(['stripe_subscription_id', 'subscription_status']);
        });
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('testimonials');
    }
};
