<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('stage')->default('inquiry'); // inquiry, pitching, negotiating, contracted, production, in_review, scheduled, invoiced, paid
            $table->json('deliverables')->nullable(); // [{platform, format, due_date, status, url}]
            $table->decimal('rate', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_terms')->nullable(); // e.g. 'Net 30', '50% upfront, 50% on publish'
            $table->string('payment_status')->default('pending'); // pending, invoiced, paid
            $table->string('coupon_code')->nullable();
            $table->text('tracking_url')->nullable();
            $table->text('key_talking_points')->nullable();
            $table->json('talking_points_checklist')->nullable(); // ["Mention free trial", "Show feature X"]
            $table->text('do_nots')->nullable();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};

