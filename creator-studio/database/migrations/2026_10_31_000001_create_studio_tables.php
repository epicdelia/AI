<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value app settings (API keys are stored encrypted).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Competitor channels on the watchlist (handle without @, or a UC... channel ID).
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('title')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        // Breakout videos found on competitor channels.
        Schema::create('outliers', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_title')->nullable();
            $table->string('title');
            $table->string('url');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('channel_median')->default(0);
            $table->decimal('outlier_score', 8, 2)->default(0);
            $table->decimal('age_days', 6, 1)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->boolean('used')->default(false); // already fed into an idea brief
            $table->timestamps();
        });

        Schema::create('idea_briefs', function (Blueprint $table) {
            $table->id();
            $table->text('summary')->nullable();
            $table->text('signals')->nullable(); // the outlier lines the AI saw
            $table->timestamps();
        });

        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_brief_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('thumbnail_text')->nullable();
            $table->text('premise')->nullable();
            $table->text('why_now')->nullable();
            $table->string('source_url')->nullable();
            $table->text('angle')->nullable();
            $table->string('format')->nullable();
            $table->string('effort')->nullable();
            $table->string('shelf_life')->nullable();
            $table->text('risk')->nullable();
            $table->string('status')->default('new'); // new, picked, dropped, scripted
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->longText('review')->nullable();
            $table->longText('transcript')->nullable();
            $table->longText('substack')->nullable();
            $table->longText('edit_brief')->nullable();
            $table->string('status')->default('draft'); // draft, reviewed, filmed, published
            $table->string('youtube_url')->nullable();
            $table->date('published_on')->nullable();
            $table->timestamps();
        });

        // The weekly commitment scoreboard (the "mentor").
        Schema::create('weeks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->date('start_date');
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, shipped, missed, late
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('video_title')->nullable();
            $table->string('youtube_url')->nullable();
            $table->date('published_on')->nullable();
            $table->unsignedBigInteger('views_7d')->nullable();
            $table->text('note')->nullable();    // her own note / excuse
            $table->text('verdict')->nullable(); // mentor verdict
            $table->timestamps();
        });

        // Dictation notes from the Flow page.
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->text('raw')->nullable();
            $table->longText('polished');
            $table->string('style')->default('auto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notes', 'weeks', 'scripts', 'ideas', 'idea_briefs', 'outliers', 'channels', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

