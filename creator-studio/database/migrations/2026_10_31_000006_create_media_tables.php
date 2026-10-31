<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generated and uploaded media, kept in private storage and served only to her session
        // (MagicAI wrote audio and images to the public disk).
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();       // audio | thumbnail | photo
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->string('provider', 30)->nullable(); // openai | elevenlabs | fal | upload
            $table->string('model', 80)->nullable();
            $table->text('prompt')->nullable();
            $table->json('meta')->nullable();
            $table->string('path', 255);
            $table->string('mime', 60);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamps();
        });

        // Shorts cut from a long-form video by Vizard (asynchronous: submit, then poll).
        Schema::create('clip_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url', 500);
            $table->string('project_id', 80)->nullable();
            $table->string('status', 20)->default('processing'); // processing | done | failed
            $table->json('options')->nullable();
            $table->json('clips')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_projects');
        Schema::dropIfExists('media_files');
    }
};
