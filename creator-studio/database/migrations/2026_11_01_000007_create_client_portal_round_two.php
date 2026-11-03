<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client portal, second round: email preferences and company teams on users, approvals and
 * expiring share links on deals, a timeline and files per deal, team invites, and a security
 * audit log (sign-ins with a device fingerprint, so a new device can be noticed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true);
            // A teammate shares the requests of the client who invited them (the team owner).
            $table->foreignId('team_owner_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('room_expires_at')->nullable();
            $table->timestamp('kit_expires_at')->nullable();
        });

        Schema::create('deal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('label');
            $table->timestamps();
            $table->index(['deal_id', 'created_at']);
        });

        Schema::create('deal_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->string('name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('team_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index('email');
        });

        // Slow AI work (idea batches, breakout scans) runs on the queue; the dashboard polls this.
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 40);
            $table->json('payload')->nullable();
            $table->string('status', 12)->default('queued'); // queued, running, done, failed
            $table->json('result')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device', 64)->nullable(); // hash of browser + network, for new-device alerts
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_tasks');
        Schema::dropIfExists('team_invites');
        Schema::dropIfExists('deal_files');
        Schema::dropIfExists('deal_events');
        Schema::table('deals', fn (Blueprint $table) => $table->dropColumn(['approved_at', 'room_expires_at', 'kit_expires_at']));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_owner_id');
            $table->dropColumn('notify_email');
        });
    }
};
