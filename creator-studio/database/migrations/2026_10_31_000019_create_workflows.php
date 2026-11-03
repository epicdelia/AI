<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Her automations: a trigger and an ordered list of steps (see App\Domains\Workflows\Catalog).
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(false);
            $table->json('nodes');                       // [{key, type, config, enabled}], trigger first
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        // One execution: which step it's on, what each step did, and (for "Wait") when to resume.
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, running, waiting, done, stopped, failed
            $table->string('trigger_ref')->nullable();   // what fired it (item:12, post:4, sched:2026-11-02), once per workflow
            $table->json('context')->nullable();
            $table->unsignedSmallInteger('cursor')->default(1);
            $table->timestamp('resume_at')->nullable();
            $table->json('log')->nullable();             // [{key, type, ok, message, at}]
            $table->boolean('dry_run')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['workflow_id', 'trigger_ref']);
            $table->index(['status', 'resume_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
