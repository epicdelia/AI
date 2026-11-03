<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per video per day (studio:stats). The YouTube Data API has no view history,
        // so these daily snapshots are what make "views at day 7" and same-age comparisons real.
        Schema::create('video_stats', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->string('title')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('captured_on');
            $table->decimal('age_days', 7, 2);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->timestamps();
            $table->unique(['video_id', 'captured_on']);
        });

        // Title, description with chapters, tags, pinned comment and end-screen notes, ready to paste.
        Schema::table('scripts', function (Blueprint $table) {
            $table->longText('publish_kit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scripts', fn (Blueprint $table) => $table->dropColumn('publish_kit'));
        Schema::dropIfExists('video_stats');
    }
};
