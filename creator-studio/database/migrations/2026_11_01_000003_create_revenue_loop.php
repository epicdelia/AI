<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The website → lead → deal → paid loop: where a lead came from, when she last touched it,
     * the proposal she sent, the call she booked, and who opened their media kit. Plus the
     * landing page funnel (counted per day, no visitor data) and sponsors seen in her niche.
     */
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('source', 40)->nullable(); // youtube, linkedin, search, direct… (null = added by hand)
            $table->json('attribution')->nullable(); // {ref, utm_*, referrer, landing_path, item}
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('follow_up_snoozed_until')->nullable();
            $table->json('proposal')->nullable();
            $table->timestamp('proposal_at')->nullable();
            $table->timestamp('meeting_at')->nullable();
            $table->string('kit_token', 40)->nullable();
            $table->unsignedInteger('kit_views')->default(0);
            $table->timestamp('kit_viewed_at')->nullable();
            $table->index('source');
            $table->unique('kit_token');
        });

        Schema::create('landing_events', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('event', 24); // visit, cta, form_start
            $table->string('source', 40)->default('direct');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['day', 'event', 'source']);
            $table->index('day');
        });

        Schema::create('sponsor_sightings', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 120);
            $table->string('domain', 120)->nullable();
            $table->string('video_id', 20);
            $table->string('video_title')->nullable();
            $table->string('channel_title')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('snippet')->nullable(); // the description line that named the sponsor
            $table->timestamps();
            $table->unique(['video_id', 'brand']);
            $table->index('brand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_sightings');
        Schema::dropIfExists('landing_events');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropUnique(['kit_token']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'attribution', 'stage_changed_at', 'last_contacted_at', 'follow_up_snoozed_until',
                'proposal', 'proposal_at', 'meeting_at', 'kit_token', 'kit_views', 'kit_viewed_at']);
        });
    }
};
