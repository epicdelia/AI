<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Visitors opening an item in the landing page's content feed, counted per item per day. */
    public function up(): void
    {
        Schema::create('landing_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('item_key', 40); // yt:<video id>, post:<social post id> or profile:<network>
            $table->string('platform', 16);
            $table->date('day');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['item_key', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_clicks');
    }
};
