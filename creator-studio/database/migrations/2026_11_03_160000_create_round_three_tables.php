<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round three:
 * - Clients: review deadlines + reminders, onboarding checklist, payment plans (net terms,
 *   deposits), overdue reminders, usage rights / whitelisting / exclusivity with end dates.
 * - Staff: brief templates per role, time tracking + hourly rates, files on tasks (review).
 * - Members: courses and lessons with progress, live events with RSVPs, win-back emails.
 * - Mobile: API tokens for the native app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->date('review_due_on')->nullable();
            $table->unsignedTinyInteger('review_reminders')->default(0);
            $table->date('review_reminded_on')->nullable();
            $table->json('onboarding')->nullable();          // {step_key: done_at}
            $table->string('payment_plan', 20)->nullable();  // net7, net15, net30, on_receipt
            $table->unsignedTinyInteger('deposit_percent')->default(0);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->timestamp('deposit_paid_at')->nullable();
            $table->date('invoice_due_on')->nullable();
            $table->unsignedTinyInteger('pay_reminders')->default(0);
            $table->date('pay_reminded_on')->nullable();
        });

        Schema::create('deal_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                       // usage, whitelisting, exclusivity
            $table->string('scope', 200)->nullable();         // e.g. "Paid social, US", "Competitors: X, Y"
            $table->unsignedSmallInteger('days');
            $table->decimal('price', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');  // active, ended
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedSmallInteger('renewals')->default(0);
            $table->timestamps();
            $table->index(['status', 'ends_on']);
        });

        Schema::table('team_tasks', function (Blueprint $table) {
            $table->unsignedInteger('minutes_logged')->default(0);
            $table->timestamp('timer_started_at')->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable(); // copied from the person when the task is billed hourly
            $table->boolean('hourly')->default(false);
            $table->text('brief')->nullable();
        });

        Schema::create('team_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('team_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('minutes');
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('team_task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('team_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('team_task_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('team_tasks')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('team_task_files')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('timecode_seconds', 8, 2)->nullable();
            $table->text('body');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('team_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('tier', 20)->default('free');
            $table->string('status', 20)->default('draft'); // draft, published
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->longText('body')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('tier', 20)->default('free'); // a free preview lesson in a Pro course
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('community_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes')->default(60);
            $table->string('join_url', 500)->nullable();
            $table->string('replay_url', 500)->nullable();
            $table->string('tier', 20)->default('free');
            $table->timestamp('reminded_day_at')->nullable();
            $table->timestamp('reminded_hour_at')->nullable();
            $table->foreignId('replay_item_id')->nullable()->constrained('community_items')->nullOnDelete();
            $table->timestamps();
            $table->index('starts_at');
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('community_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('community_seen_at')->nullable();
            $table->timestamp('winback_sent_at')->nullable();
        });

        // The native app signs in with a bearer token (only its SHA-256 is stored).
        Schema::create('mobile_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mobile_tokens', 'event_rsvps', 'community_events', 'lesson_progress', 'course_lessons', 'courses',
            'team_rates', 'team_task_notes', 'team_task_files', 'team_time_entries', 'deal_rights'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['community_seen_at', 'winback_sent_at']));
        Schema::table('team_tasks', fn (Blueprint $t) => $t->dropColumn(['minutes_logged', 'timer_started_at', 'hourly_rate', 'hourly', 'brief']));
        Schema::table('deals', fn (Blueprint $t) => $t->dropColumn(['review_due_on', 'review_reminders', 'review_reminded_on', 'onboarding', 'payment_plan',
            'deposit_percent', 'deposit_amount', 'deposit_paid_at', 'invoice_due_on', 'pay_reminders', 'pay_reminded_on']));
    }
};
