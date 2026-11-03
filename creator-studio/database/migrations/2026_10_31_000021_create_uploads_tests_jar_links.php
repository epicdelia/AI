<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Videos sent to YouTube from the studio (resumable: picks up where it stopped).
        Schema::create('video_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('privacy')->default('private'); // private | unlisted | public | scheduled
            $table->timestamp('publish_at')->nullable();
            $table->string('path');                      // the video file in private storage
            $table->string('mime')->default('video/mp4');
            $table->unsignedBigInteger('bytes');         // full size
            $table->unsignedBigInteger('received')->default(0); // bytes received from her browser
            $table->unsignedBigInteger('sent')->default(0);     // bytes accepted by YouTube
            $table->text('upload_url')->nullable();      // YouTube's resumable session
            $table->foreignId('thumbnail_media_id')->nullable();
            $table->string('status')->default('receiving'); // receiving, queued, uploading, done, failed
            $table->string('video_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Title test & compare: titles take turns on a live video; the best views per hour wins.
        Schema::create('title_tests', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->string('original_title');
            $table->json('titles');
            $table->json('windows')->nullable();         // [{title_index, start, end, views_start, views_end}]
            $table->unsignedSmallInteger('current')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->unsignedBigInteger('views_at_start')->default(0);
            $table->unsignedSmallInteger('hours')->default(24);
            $table->unsignedSmallInteger('rounds')->default(2);
            $table->string('status')->default('running'); // running, done, stopped, failed
            $table->unsignedSmallInteger('winner')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // The memory jar: a question a day, answered by both of them.
        Schema::create('memory_jar', function (Blueprint $table) {
            $table->id();
            $table->date('asked_on')->unique();
            $table->string('question');
            $table->text('her_answer')->nullable();
            $table->text('his_answer')->nullable();
            $table->timestamps();
        });

        // Link in bio: her links, and clicks per day.
        Schema::create('bio_links', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('url');
            $table->string('icon')->nullable();          // a network key or emoji
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });
        Schema::create('bio_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bio_link_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['bio_link_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bio_clicks');
        Schema::dropIfExists('bio_links');
        Schema::dropIfExists('memory_jar');
        Schema::dropIfExists('title_tests');
        Schema::dropIfExists('video_uploads');
    }
};
