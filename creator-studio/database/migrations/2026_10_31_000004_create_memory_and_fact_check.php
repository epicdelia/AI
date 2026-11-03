<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scripts', function (Blueprint $table) {
            $table->longText('fact_check')->nullable()->after('review');
            $table->timestamp('fact_checked_at')->nullable()->after('fact_check');
        });

        // Memory: chunks of her own past work (scripts, transcripts, Library, notes) for search.
        // Ported from MagicAI's pdf_data / chatbot_data_vectors (content + JSON vector).
        Schema::create('memory_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20);  // script | transcript | document | note | idea
            $table->unsignedBigInteger('source_id');
            $table->string('title', 255);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('content');
            $table->longText('vector')->nullable();  // JSON float array, null = keyword search only
            $table->string('model', 80)->nullable();
            $table->string('hash', 40);              // sha1 of the source text, to skip unchanged sources
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_chunks');
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropColumn(['fact_check', 'fact_checked_at']);
        });
    }
};
