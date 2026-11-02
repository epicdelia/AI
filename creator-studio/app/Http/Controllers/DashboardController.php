<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Idea;
use App\Models\Note;
use App\Models\Outlier;
use App\Models\Script;
use App\Models\Setting;
use App\Models\Week;
use App\Services\Llm;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $weeks = Week::orderBy('number')->get();
        $done = $weeks->whereIn('status', ['shipped', 'late']);

        return view('dashboard', [
            'currentWeek' => Week::current(),
            'weeks' => $weeks,
            'shipped' => $weeks->where('status', 'shipped')->count(),
            'missed' => $weeks->where('status', 'missed')->count(),
            'scored' => $weeks->where('status', '!=', 'pending')->count(),
            'streak' => $this->streak($weeks),
            'pickedIdeas' => Idea::where('status', 'picked')->latest()->take(5)->get(),
            'newIdeas' => Idea::where('status', 'new')->count(),
            'scripts' => Script::whereIn('status', ['draft', 'reviewed', 'filmed'])->latest('updated_at')->take(5)->get(),
            'outliers' => Outlier::orderByDesc('outlier_score')->where('created_at', '>=', now()->subDays(7))->take(5)->get(),
            'notes' => Note::latest()->take(3)->get(),
            'setup' => [
                'Your channel' => Setting::has('channel_id'),
                'YouTube API key' => Setting::has('youtube_api_key'),
                'AI key' => Llm::configured(),
                'Competitor channels' => Channel::exists(),
                'Weekly plan' => $weeks->isNotEmpty(),
            ],
            'doneCount' => $done->count(),
        ]);
    }

    private function streak($weeks): int
    {
        $n = 0;
        foreach ($weeks->where('status', '!=', 'pending')->sortByDesc('number') as $w) {
            if ($w->status !== 'shipped') {
                break;
            }
            $n++;
        }

        return $n;
    }
}
