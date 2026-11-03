<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every AI call, for the usage card in Settings.
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 40)->index();
            $table->string('engine', 30);
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('ok')->default(true);
            $table->string('error', 255)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        // Her own Toolkit templates: a prompt with {placeholders} and the fields that fill them.
        Schema::create('custom_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            $table->string('icon', 16)->default('✨');
            $table->json('fields');
            $table->text('prompt');
            $table->timestamps();
        });

        // Saved Toolkit / YouTube / chat outputs (the Library).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('tool', 60)->nullable()->index();
            $table->longText('content');
            $table->json('inputs')->nullable();
            $table->boolean('favorite')->default(false);
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Producer chat threads.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->default('New chat');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // user | assistant
            $table->longText('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['chat_messages', 'conversations', 'documents', 'custom_tools', 'ai_usages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
