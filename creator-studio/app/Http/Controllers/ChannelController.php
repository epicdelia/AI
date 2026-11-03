<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Outlier;
use App\Models\Setting;
use App\Services\YouTube;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

class ChannelController extends Controller
{
    public function index()
    {
        return view('channels', [
            'channels' => Channel::withCount('outliers')->orderBy('handle')->get(),
            'outliers' => Outlier::with('channel')->orderByDesc('created_at')->orderByDesc('outlier_score')->paginate(30),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'handles' => 'required|string|max:5000',
        ]);
        $added = 0;
        foreach (preg_split('/[\s,]+/', $request->input('handles'), -1, PREG_SPLIT_NO_EMPTY) as $raw) {
            $handle = Channel::normalize($raw);
            if ($handle !== '' && !Channel::where('handle', $handle)->exists()) {
                Channel::create([
                    'handle' => $handle,
                ]);
                $added++;
            }
        }

        return back()->with('status', $added ? "Added {$added} channel(s). Hit \"Find breakouts\" to scan them." : 'Those channels are already on the list.');
    }

    public function toggle(Channel $channel)
    {
        $channel->update([
            'active' => !$channel->active,
        ]);

        return back();
    }

    public function destroy(Channel $channel)
    {
        $channel->delete();

        return back()->with('status', "Removed {$channel->handle}.");
    }

    /**
     * Port of find_outliers.py: scan every active channel and store new breakouts.
     */
    public function scan(YouTube $yt)
    {
        $channels = Channel::where('active', true)->get();
        if ($channels->isEmpty()) {
            return back()->withErrors([
                'scan' => 'Add some competitor channels first.',
            ]);
        }
        @set_time_limit(300);

        try {
            $out = $yt->findOutliers($channels->pluck('handle')->all(), [
                'lookback_days' => Setting::get('lookback_days'),
                'min_outlier_score' => Setting::get('min_outlier_score'),
                'min_views' => Setting::get('min_views'),
            ], null, Outlier::pluck('video_id')->all());
        } catch (Throwable $e) {
            return back()->withErrors([
                'scan' => $e->getMessage(),
            ]);
        }

        $byHandle = $channels->keyBy('handle');
        foreach ($out['channels'] as $handle => $info) {
            $channel = $byHandle->get($handle);
            $channel?->update([
                'title' => $info['title'] ?? $channel->title,
                'last_error' => $info['error'],
                'last_scanned_at' => now(),
            ]);
        }
        foreach ($out['results'] as $v) {
            Outlier::create([
                'video_id' => $v['id'],
                'channel_id' => $byHandle->get($v['handle'])?->id,
                'channel_title' => $v['channel'],
                'title' => $v['title'],
                'url' => $v['url'],
                'views' => $v['views'],
                'channel_median' => (int)$v['channel_median'],
                'outlier_score' => $v['outlier_score'],
                'age_days' => round($v['age_days'], 1),
                'published_at' => CarbonImmutable::parse($v['published_at']),
            ]);
        }
        $errors = collect($out['channels'])->filter(fn ($i) => $i['error'])->count();
        $msg = count($out['results']) . " new breakout video(s) found across {$channels->count()} channel(s).";
        if ($errors) {
            $msg .= " {$errors} channel(s) had a problem (see the list).";
        }

        return back()->with('status', $msg);
    }

    public function destroyOutlier(Outlier $outlier)
    {
        $outlier->delete();

        return back();
    }
}
