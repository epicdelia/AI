<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The post's cover image from the network's API (refreshed by each sync, since those links expire). */
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('thumbnail_url', 2000)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('thumbnail_url');
        });
    }
};
