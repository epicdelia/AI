<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copilot_threads', function (Blueprint $table) {
            $table->longText('summary')->nullable();               // compacted earlier turns
            $table->unsignedBigInteger('summary_upto_id')->nullable();
            $table->string('kind', 20)->default('chat');           // chat | briefing
        });

        // Change sets: all proposals from one answer share a batch (the user message id).
        Schema::table('copilot_actions', function (Blueprint $table) {
            $table->string('batch', 40)->nullable()->index();
        });

        // Missions: resumable, checkpointed workflows ("Ship this week's video").
        Schema::create('copilot_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('title', 200);
            $table->string('status', 12)->default('active'); // active | done | cancelled
            $table->unsignedSmallInteger('step')->default(0);
            $table->json('steps');
            $table->json('data')->nullable();                // choices so far: idea, title, plan, script...
            $table->timestamps();
        });

        // Signals the Copilot learns her preferences from (apply / dismiss / feedback / explicit).
        Schema::create('copilot_signals', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);          // applied | dismissed | undone | rating | feedback | remember
            $table->text('detail');
            $table->boolean('learned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_signals');
        Schema::dropIfExists('copilot_missions');
        // SQLite can't drop a column that still has an index on it.
        Schema::table('copilot_actions', function (Blueprint $t) {
            $t->dropIndex(['batch']);
            $t->dropColumn('batch');
        });
        Schema::table('copilot_threads', fn (Blueprint $t) => $t->dropColumn(['summary', 'summary_upto_id', 'kind']));
    }
};
