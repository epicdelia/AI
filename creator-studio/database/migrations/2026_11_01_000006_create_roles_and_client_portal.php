<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles (App\Enums\Role), two-factor sign-in, client accounts linked to their deals, and a
 * message thread per deal between her and the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->after('email');
            $table->string('company', 150)->nullable()->after('role');
            $table->string('phone', 40)->nullable()->after('company');
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // a code works once
            $table->index('role');
        });

        // Every account so far is hers: they become admins (with a proven email).
        DB::table('users')->update(['role' => 'admin']);
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('deal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->timestamp('read_at')->nullable(); // read by the other side
            $table->timestamps();
            $table->index(['deal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_messages');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'company', 'phone', 'disabled_at', 'last_login_at', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
