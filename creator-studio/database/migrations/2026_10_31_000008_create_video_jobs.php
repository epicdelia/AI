<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // B-roll clips generated through fal.ai's queue (submit → status → result), as in
        // MagicAI's Packages\FalAI models. The finished video is copied into media_files.
        Schema::create('video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model', 40);
            $table->text('prompt');
            $table->json('params')->nullable();
            $table->string('request_id', 80)->nullable();
            $table->string('status_url', 500)->nullable();
            $table->string('response_url', 500)->nullable();
            $table->string('status', 20)->default('queued'); // queued | running | done | failed
            $table->foreignId('media_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error', 300)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_jobs');
    }
};
