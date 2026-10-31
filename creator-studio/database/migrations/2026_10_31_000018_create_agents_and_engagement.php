<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Social media agents: each plans, writes and (if allowed) publishes posts for some of her
        // networks, and keeps a list of lessons learned from results and from her edits.
        Schema::create('social_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('platforms');                 // ['linkedin', 'threads', ...]
            $table->string('site_url')->nullable();
            $table->text('brand');                     // what she / the brand is about
            $table->text('audience')->nullable();      // null = the agent decides
            $table->json('post_types');                // informative, tips_and_tricks, ...
            $table->json('options')->nullable();       // hashtags, hashtag_count, emoji, images, cta, language, tone
            $table->unsignedSmallInteger('posts_per_week')->default(3); // per network
            $table->string('approval')->default('review'); // review | auto
            $table->boolean('active')->default(true);
            $table->json('lessons')->nullable();       // [{text, evidence}]
            $table->timestamp('learned_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        // What agents did (and what needs her): the notifications drawer.
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                    // drafted, approved, rejected, published, failed, learned, comment, dm
            $table->string('platform')->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['read_at', 'created_at']);
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('social_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approval')->nullable();    // pending | approved (agent posts only)
            $table->longText('agent_original')->nullable(); // the agent's text, to learn from her edits
            $table->text('publish_error')->nullable();
        });

        // Comments and DMs from her connected accounts, and the replies to them.
        Schema::create('engagement_items', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('kind');                    // comment | dm
            $table->string('external_id');
            $table->string('thread_id')->nullable();   // post id (comments) or conversation id (DMs)
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author')->nullable();
            $table->string('author_id')->nullable();
            $table->text('text');
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('new');  // new, drafted, replied, ignored, failed
            $table->string('intent')->nullable();      // question, praise, lead, spam, other
            $table->text('reply')->nullable();
            $table->text('reply_draft')->nullable();
            $table->string('replied_by')->nullable();  // her | rule | ai
            $table->foreignId('engagement_rule_id')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['platform', 'kind', 'external_id']);
            $table->index(['status', 'received_at']);
        });

        // Her automations: "if a comment says GUIDE, reply and DM the link", "draft replies to questions".
        Schema::create('engagement_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('platforms');
            $table->string('trigger');                 // keyword | question | any_comment | any_dm
            $table->json('keywords')->nullable();
            $table->string('reply_mode')->default('ai'); // ai | template | none
            $table->text('reply_template')->nullable();
            $table->text('instructions')->nullable();  // guidance for AI replies
            $table->text('dm_text')->nullable();       // private reply / DM to send (Instagram)
            $table->boolean('auto_send')->default(false); // false = drafts wait in the inbox
            $table->boolean('active')->default(true);
            $table->unsignedInteger('max_per_hour')->default(20);
            $table->unsignedInteger('fired')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_rules');
        Schema::dropIfExists('engagement_items');
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_agent_id');
            $table->dropColumn(['approval', 'agent_original', 'publish_error']);
        });
        Schema::dropIfExists('agent_events');
        Schema::dropIfExists('social_agents');
    }
};
