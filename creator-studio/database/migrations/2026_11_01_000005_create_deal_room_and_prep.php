<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closing deals: a private client page per deal (proposal, accept, pay) that tells her when
     * it's opened, a prep brief before intro calls, and why a deal was lost (for win rates).
     */
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('room_token', 40)->nullable();
            $table->unsignedInteger('room_views')->default(0);
            $table->timestamp('room_viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_by')->nullable(); // the name they typed to accept
            $table->json('prep')->nullable();
            $table->timestamp('prep_at')->nullable();
            $table->string('lost_reason', 40)->nullable();
            $table->unique('room_token');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropUnique(['room_token']);
            $table->dropColumn(['room_token', 'room_views', 'room_viewed_at', 'accepted_at', 'accepted_by', 'prep', 'prep_at', 'lost_reason']);
        });
    }
};
