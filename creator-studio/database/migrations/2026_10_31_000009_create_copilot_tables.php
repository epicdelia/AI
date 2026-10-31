<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copilot conversations, grouped into projects (footer "Projects" / "History").
        Schema::create('copilot_threads', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->default('New chat');
            $table->string('project', 80)->nullable()->index();
            $table->string('mode', 10)->default('agent'); // agent | ask
            $table->string('model', 120)->nullable();      // null = the model in Settings
            $table->timestamps();
        });

        // One row per user / assistant / tool turn, in the provider-neutral shape the agent loop uses.
        Schema::create('copilot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);                // user | assistant | tool
            $table->longText('content')->nullable();
            $table->json('meta')->nullable();          // tool_calls, tool name/id, page context, attachments
            $table->timestamps();
        });

        // Changes the agent proposes; nothing touches her data until she presses Apply.
        Schema::create('copilot_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 40);
            $table->json('args');
            $table->string('summary', 300);
            $table->string('status', 12)->default('pending'); // pending | applied | dismissed | undone | failed
            $table->json('result')->nullable();               // what changed, plus what's needed to undo it
            $table->timestamps();
        });

        Schema::create('copilot_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('copilot_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rating', 8)->nullable(); // up | down
            $table->text('text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['copilot_feedback', 'copilot_actions', 'copilot_messages', 'copilot_threads'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
