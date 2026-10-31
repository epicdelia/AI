<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // News/blog feeds in her niche: a second idea signal next to YouTube breakouts.
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->string('title')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('guid', 500);
            $table->string('title', 500);
            $table->string('link', 1000)->nullable();
            $table->text('summary')->nullable();
            $table->string('image', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('used')->default(false); // already fed into an idea brief
            $table->timestamps();
            $table->unique(['feed_id', 'guid']);
            $table->index(['used', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('feeds');
    }
};
