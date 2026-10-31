<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each call is measured in, after MagicAI's AITokenType (word, character, image,
        // minute, ...) and Entity Calculate concerns: token for text models, character for
        // read-aloud, image for mockups, search for web search, file for transcription.
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->string('unit', 20)->default('token')->after('model');
            $table->unsignedBigInteger('quantity')->nullable()->after('unit');
        });

        DB::table('ai_usages')->update(['quantity' => DB::raw('coalesce(input_tokens, 0) + coalesce(output_tokens, 0)')]);
        DB::table('ai_usages')->where('feature', 'read_aloud')->update(['unit' => 'character', 'quantity' => DB::raw('input_tokens'), 'input_tokens' => null]);
        DB::table('ai_usages')->where('feature', 'thumbnail')->update(['unit' => 'image', 'quantity' => 1]);
        DB::table('ai_usages')->where('feature', 'web_search')->update(['unit' => 'search', 'quantity' => 1]);
        DB::table('ai_usages')->where('feature', 'transcription')->update(['unit' => 'file', 'quantity' => 1]);
        DB::table('ai_usages')->where('feature', 'shorts')->update(['unit' => 'job', 'quantity' => 1]);
    }

    public function down(): void
    {
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropColumn(['unit', 'quantity']);
        });
    }
};
