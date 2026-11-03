<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Posts for LinkedIn, Instagram, X and YouTube (Shorts, community posts): drafted here,
        // copied out to each app by hand, then marked posted with the link and her numbers.
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('platform');              // linkedin, instagram, x, youtube
            $table->string('format');                // see Platforms::FORMATS
            $table->string('title')->nullable();     // her own label, never published
            $table->longText('body')->nullable();    // post text or caption
            $table->json('parts')->nullable();       // thread posts, carousel slides, or Reel/Short beats
            $table->string('status')->default('draft'); // idea, draft, scheduled, posted
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual'); // manual, repurpose, copilot
            $table->json('metrics')->nullable();     // impressions, likes, comments, shares, saves
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
