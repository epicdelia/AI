<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Follower counts over time, one row per platform per day. YouTube subscribers are recorded
        // by studio:stats; the others she types in (reading them needs each platform's sign-in).
        Schema::create('follower_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->unsignedBigInteger('count');
            $table->date('recorded_on');
            $table->string('source')->default('manual'); // manual, youtube
            $table->timestamps();
            $table->unique(['platform', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follower_snapshots');
    }
};
