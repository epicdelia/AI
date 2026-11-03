<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every role gets its own depth:
 * - Community (members): real, admin-managed content, one vote per member, points & levels,
 *   paid tiers (Stripe subscription), digests and "answered" notices.
 * - Staff: production tasks in a table (real assignees, due dates, payouts), invites by email.
 * - Clients: Stripe customer (self-serve billing portal), intake answers per request.
 * - Admin: a light CRM (contacts + activity timeline).
 * - Scripts: revisions and presence for editing together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                 // kit, blueprint, dispatch, perk, showcase, question
            $table->string('title', 200);
            $table->text('body')->nullable();           // description / summary / the question
            $table->text('answer')->nullable();         // her answer to a question
            $table->timestamp('answered_at')->nullable();
            $table->string('url', 500)->nullable();     // repo, article, perk link
            $table->string('url2', 500)->nullable();    // demo link
            $table->string('code', 200)->nullable();    // perk code or clone command
            $table->json('tags')->nullable();
            $table->json('meta')->nullable();           // features, components, category, badge…
            $table->string('tier', 20)->default('free'); // free | pro
            $table->string('status', 20)->default('published'); // published | pending | hidden
            $table->boolean('featured')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('upvotes')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'published_at']);
        });

        Schema::create('community_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('community_items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'item_id']);
        });

        Schema::create('community_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('reason', 40);
            $table->foreignId('item_id')->nullable()->constrained('community_items')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('community_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tier', 20)->default('pro');
            $table->string('status', 20)->default('active'); // active | cancelled | comped
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });

        Schema::create('team_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('stage', 20)->default('idea'); // idea, script, filming, editing, review, scheduled, published
            $table->string('role_label', 60)->nullable(); // Video Editor, Thumbnail Artist…
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('script_id')->nullable()->constrained('scripts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('payout_amount', 10, 2)->default(0);
            $table->string('payout_currency', 3)->default('USD');
            $table->string('payout_status', 20)->default('none'); // none, pending, approved, paid
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_ref', 120)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['assignee_id', 'stage']);
            $table->index(['payout_status']);
        });

        Schema::create('staff_invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name', 100);
            $table->string('role_label', 60)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index('email');
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email')->nullable()->index();
            $table->string('company', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('source', 40)->nullable();     // website, community, referral, event, manual…
            $table->string('stage', 20)->default('lead'); // lead, contacted, qualified, customer, lost
            $table->json('tags')->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // their account, if any
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->index(['stage', 'next_follow_up_at']);
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('kind', 20); // note, call, email, meeting, task
            $table->text('body');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['contact_id', 'created_at']);
        });

        Schema::create('script_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->longText('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['script_id', 'revision']);
        });

        Schema::create('script_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cursor_line')->nullable();
            $table->timestamp('seen_at');
            $table->unique(['script_id', 'user_id']);
        });

        Schema::table('scripts', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
            $table->boolean('community_digest')->default(true);
            $table->timestamp('community_digest_at')->nullable();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
            $table->json('intake')->nullable();
            $table->timestamp('intake_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'intake', 'intake_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'community_digest', 'community_digest_at']);
        });
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
        foreach (['script_presences', 'script_revisions', 'crm_activities', 'crm_contacts', 'staff_invites', 'team_tasks',
            'community_memberships', 'community_points', 'community_votes', 'community_items'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
