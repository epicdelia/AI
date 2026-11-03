<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Her connected social accounts (one per network). Tokens are encrypted with APP_KEY
        // and never leave the server.
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique();
            $table->string('external_id')->nullable();
            $table->string('handle')->nullable();
            $table->string('name')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('connected'); // connected, expired, error
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();               // follower count, channel summary, top videos
            $table->timestamps();
        });

        // Posts pulled from a connected account are matched by the network's own post id.
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('external_id')->nullable();
            $table->index(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropIndex(['platform', 'external_id']);
            $table->dropColumn('external_id');
        });
        Schema::dropIfExists('social_accounts');
    }
};
