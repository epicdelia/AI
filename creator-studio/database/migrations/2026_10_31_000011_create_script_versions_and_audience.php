<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshots of a script body, taken before it's overwritten (see ScriptVersion::beforeChange).
        Schema::create('script_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->unsignedInteger('words')->default(0);
            $table->string('source')->default('auto'); // auto, manual, restore
            $table->string('label')->nullable();
            $table->timestamps();
            $table->index(['script_id', 'id']);
        });

        // What viewers ask for in a video's comments (her own videos, or any video she analyses).
        Schema::create('audience_insights', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('video_title')->nullable();
            $table->string('url');
            $table->boolean('own')->default(false); // one of her own videos
            $table->unsignedInteger('comment_count')->default(0);
            $table->text('summary')->nullable();
            $table->json('items')->nullable();
            $table->boolean('used')->default(false); // already fed into an idea brief
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_insights');
        Schema::dropIfExists('script_versions');
    }
};
