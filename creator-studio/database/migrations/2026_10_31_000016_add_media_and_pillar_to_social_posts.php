<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // [{id, url, mime, name, alt}] of media_files attached to the post, in order.
            $table->json('media')->nullable();
            // One of her content pillars (Settings → Social), for the mix in Analytics.
            $table->string('pillar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['media', 'pillar']);
        });
    }
};
