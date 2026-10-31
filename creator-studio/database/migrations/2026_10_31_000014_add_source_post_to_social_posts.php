<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A "fresh take" of one of her best posts points back at the original.
        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('source_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_post_id');
        });
    }
};
