<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Her Head Start talk (LinkedIn, 14 Oct 2024) with its stage photo, for the On Stage slider. */
    public function up(): void
    {
        if (DB::table('talks')->where('event', 'Head Start')->exists()) {
            return;
        }
        DB::table('talks')->insert([
            'event' => 'Head Start', 'title' => 'From software engineer to technical consultant', 'kind' => 'fireside',
            'description' => 'With students at Head Start (organized by Sajjaad Khader): going from software engineer to technical consultant, seven years as a digital nomad, and why six months of hands-on work can teach more than years of study.',
            'date' => '2024-10-14', 'time' => null, 'venue' => null, 'city' => null,
            'url' => 'https://www.linkedin.com/posts/delialazarescu_publicspeaking-remotework-softwareengineer-activity-7251637912553897984-cmvt',
            'image' => '/images/speaking/head-start-2024.jpg', 'featured' => false, 'visible' => true,
            'position' => (int) DB::table('talks')->max('position') + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('talks')->where('event', 'Head Start')->where('image', '/images/speaking/head-start-2024.jpg')->delete();
    }
};
