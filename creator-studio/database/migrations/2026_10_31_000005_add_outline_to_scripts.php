<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The plan approved in the script wizard: {topic, title, minutes, chapters: [{title, beat}]}.
        Schema::table('scripts', function (Blueprint $table) {
            $table->json('outline')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropColumn('outline');
        });
    }
};
