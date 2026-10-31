<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swarm_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_agent_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('goal');
            $table->string('status', 20)->default('pending'); // pending, running, paused, completed, failed
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->json('steps');
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['social_agent_id', 'status']);
        });

        Schema::create('agent_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('system_prompt');
            $table->unsignedTinyInteger('score')->default(0); // 0-100
            $table->json('eval_breakdown')->nullable();
            $table->string('changes_summary', 300)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['social_agent_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_prompt_versions');
        Schema::dropIfExists('swarm_missions');
    }
};

