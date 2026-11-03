<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add high-performance compound indexes for hot query paths.
     */
    public function up(): void
    {
        // 1. Ideas query optimization (status filtering, dashboard counts, ordering)
        if (Schema::hasTable('ideas')) {
            Schema::table('ideas', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_ideas_status_created');
            });
        }

        // 2. Outliers breakout lookup and sorting
        if (Schema::hasTable('outliers')) {
            Schema::table('outliers', function (Blueprint $table) {
                $table->index(['outlier_score'], 'idx_outliers_score');
                $table->index(['used', 'outlier_score'], 'idx_outliers_used_score');
            });
        }

        // 3. Scripts status pipeline & dashboard recency
        if (Schema::hasTable('scripts')) {
            Schema::table('scripts', function (Blueprint $table) {
                $table->index(['status', 'updated_at'], 'idx_scripts_status_updated');
            });
        }

        // 4. Deals active pipeline and deliverables tracking
        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->index(['stage', 'updated_at'], 'idx_deals_stage_updated');
            });
        }

        // 5. Social posts approval queue and scheduling poller
        if (Schema::hasTable('social_posts')) {
            Schema::table('social_posts', function (Blueprint $table) {
                $table->index(['approval', 'social_agent_id'], 'idx_social_posts_approval_agent');
                $table->index(['status', 'scheduled_for'], 'idx_social_posts_status_scheduled');
            });
        }

        // 6. Notes recency
        if (Schema::hasTable('notes')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->index(['created_at'], 'idx_notes_created_at');
            });
        }

        // 7. Scoreboard weeks lookup
        if (Schema::hasTable('weeks')) {
            Schema::table('weeks', function (Blueprint $table) {
                $table->index(['status', 'number'], 'idx_weeks_status_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ideas')) {
            Schema::table('ideas', function (Blueprint $table) {
                $table->dropIndex('idx_ideas_status_created');
            });
        }
        if (Schema::hasTable('outliers')) {
            Schema::table('outliers', function (Blueprint $table) {
                $table->dropIndex('idx_outliers_score');
                $table->dropIndex('idx_outliers_used_score');
            });
        }
        if (Schema::hasTable('scripts')) {
            Schema::table('scripts', function (Blueprint $table) {
                $table->dropIndex('idx_scripts_status_updated');
            });
        }
        if (Schema::hasTable('deals')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->dropIndex('idx_deals_stage_updated');
            });
        }
        if (Schema::hasTable('social_posts')) {
            Schema::table('social_posts', function (Blueprint $table) {
                $table->dropIndex('idx_social_posts_approval_agent');
                $table->dropIndex('idx_social_posts_status_scheduled');
            });
        }
        if (Schema::hasTable('notes')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropIndex('idx_notes_created_at');
            });
        }
        if (Schema::hasTable('weeks')) {
            Schema::table('weeks', function (Blueprint $table) {
                $table->dropIndex('idx_weeks_status_number');
            });
        }
    }
};

