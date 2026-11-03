<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'credit_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('credit_balance', 12, 4)->default(100.0000)->after('referral_credits');
            });
        }

        if (!Schema::hasTable('user_api_keys')) {
            Schema::create('user_api_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 50); // gemini, openai, anthropic, deepseek, xai, elevenlabs, fal, etc.
                $table->text('api_key');        // Encrypted via Crypt::encryptString
                $table->string('base_url')->nullable(); // Optional custom base URL or Azure/OpenRouter endpoint
                $table->string('default_model')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
                $table->index(['user_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('user_usage_credits')) {
            Schema::create('user_usage_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('model_key', 100);
                $table->string('feature', 100)->nullable();
                $table->string('unit', 50)->default('token'); // token, image, second, character
                $table->unsignedBigInteger('quantity')->default(0);
                $table->decimal('unit_price', 10, 6)->default(0);
                $table->decimal('total_cost', 10, 4)->default(0);
                $table->boolean('byok')->default(false); // true if user provided their own key
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['user_id', 'model_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_usage_credits');
        Schema::dropIfExists('user_api_keys');

        if (Schema::hasColumn('users', 'credit_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('credit_balance');
            });
        }
    }
};

