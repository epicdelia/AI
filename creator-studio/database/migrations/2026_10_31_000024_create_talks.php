<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Her talks (keynotes, panels, workshops…) for the landing page's "On Stage", managed from the dashboard. */
    public function up(): void
    {
        Schema::create('talks', function (Blueprint $table) {
            $table->id();
            $table->string('event', 150);
            $table->string('title', 200)->nullable();       // the talk's own title
            $table->string('kind', 20)->default('keynote'); // keynote, panel, workshop, fireside, podcast, webinar
            $table->text('description')->nullable();
            $table->date('date')->nullable();
            $table->string('time', 40)->nullable();
            $table->string('venue', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('url', 500)->nullable();          // post, recording or event page
            $table->string('image', 300)->nullable();        // /images/... (bundled) or talks/... (uploaded)
            $table->boolean('featured')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('talks')->insert([
            [
                'event' => 'KM & AI Summit 2025', 'title' => 'AI for Enterprise KM & Collaboration', 'kind' => 'keynote',
                'description' => 'How organizations can use AI to transform knowledge management, with actionable strategies teams can put to work.',
                'date' => '2025-03-19', 'time' => '9:00 to 10:00 a.m.', 'venue' => 'JW Marriott Camelback Inn Resort & Spa', 'city' => 'Scottsdale, AZ',
                'url' => 'https://www.linkedin.com/posts/delialazarescu_kmsummit2025-kmsummit2025-activity-7299912308879769600-mTDU',
                'image' => '/images/speaking/km-ai-summit-2025.jpg', 'featured' => true, 'visible' => true, 'position' => 0, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'event' => 'Nomad Cruise', 'title' => null, 'kind' => 'keynote',
                'description' => 'Keynote on building a tech career without a fixed address: engineering, content, and revenue.',
                'date' => null, 'time' => null, 'venue' => null, 'city' => null, 'url' => null, 'image' => null,
                'featured' => false, 'visible' => true, 'position' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'event' => 'Vently Panel', 'title' => null, 'kind' => 'panel',
                'description' => 'Panel on creator-led businesses and what happens when engineers learn distribution.',
                'date' => null, 'time' => null, 'venue' => null, 'city' => null, 'url' => null, 'image' => null,
                'featured' => false, 'visible' => true, 'position' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'event' => 'Digital Garage Tokyo', 'title' => null, 'kind' => 'workshop',
                'description' => 'Workshop on practical AI for non-technical teams: making the intimidating feel obvious.',
                'date' => null, 'time' => null, 'venue' => null, 'city' => 'Tokyo', 'url' => null, 'image' => null,
                'featured' => false, 'visible' => true, 'position' => 3, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('talks');
    }
};
