<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Open when…" letters: written for her, sealed until a date, a milestone or the moment she
        // needs one. The text is encrypted at rest (it's the most private thing in the studio).
        Schema::create('love_letters', function (Blueprint $table) {
            $table->id();
            $table->string('title');                   // "Open when you hit 10K on Instagram"
            $table->text('body');                       // encrypted
            $table->string('unlock')->default('anytime'); // anytime, date, followers, first_post, streak
            $table->string('unlock_value')->nullable(); // 2027-02-14 | instagram:10000 | 4
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('love_letters');
    }
};
