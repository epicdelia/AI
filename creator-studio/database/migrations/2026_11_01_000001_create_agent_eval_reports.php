<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_eval_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_agent_id')->constrained()->cascadeOnDelete();
            $table->string('scenario'); // breaking_trend, audience_faq, sponsor_integration, cliche_resistance
            $table->unsignedTinyInteger('overall_score'); // 0-100
            $table->json('rubric'); // hook, voice, retention, cliche_freedom, safety
            $table->text('output_sample');
            $table->json('feedback')->nullable();
            $table->timestamps();

            $table->index(['social_agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_reports');
    }
};

