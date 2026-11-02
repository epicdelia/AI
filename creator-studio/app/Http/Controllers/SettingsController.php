<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Llm;
use App\Services\YouTube;
use Illuminate\Http\Request;
use Throwable;

class SettingsController extends Controller
{
    private const FIELDS = [
        'creator_name' => 'nullable|string|max:100',
        'channel_id' => 'nullable|string|max:200',
        'niche' => 'nullable|string|max:500',
        'audience' => 'nullable|string|max:500',
        'angle' => 'nullable|string|max:2000',
        'voice_excerpts' => 'nullable|string|max:20000',
        'llm_base_url' => 'nullable|url|max:300',
        'llm_model' => 'nullable|string|max:100',
        'lookback_days' => 'nullable|integer|min:1|max:30',
        'min_outlier_score' => 'nullable|numeric|min:1|max:100',
        'min_views' => 'nullable|integer|min:0',
        'baseline_views' => 'nullable|integer|min:0',
        'commitment' => 'nullable|string|max:200',
        'timezone' => 'nullable|timezone',
    ];

    public function edit()
    {
        return view('settings', [
            's' => fn (string $k) => Setting::get($k, ''),
            'secrets' => collect(Setting::SECRET)->mapWithKeys(fn ($k) => [
                $k => Setting::has($k),
            ]),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(self::FIELDS + [
            'youtube_api_key' => 'nullable|string|max:300',
            'llm_api_key' => 'nullable|string|max:500',
            'assemblyai_api_key' => 'nullable|string|max:300',
        ]);
        foreach (array_keys(self::FIELDS) as $key) {
            if ($request->has($key)) {
                $value = $data[$key] ?? null;
                Setting::put($key, $key === 'channel_id' && $value ? \App\Models\Channel::normalize($value) : $value);
            }
        }
        // Secret fields: blank means "keep the current key"; the "clear" checkbox removes it.
        foreach (Setting::SECRET as $key) {
            if ($request->boolean("clear_{$key}")) {
                Setting::put($key, null);
            } elseif (!empty($data[$key])) {
                Setting::put($key, $data[$key]);
            }
        }

        return back()->with('status', 'Settings saved.');
    }

    /**
     * Plain-English check that each key actually works.
     */
    public function test(YouTube $yt, Llm $llm)
    {
        $results = [];

        try {
            if (!Setting::has('youtube_api_key')) {
                throw new \RuntimeException('No key saved.');
            }
            $channel = Setting::get('channel_id');
            if ($channel) {
                [, $title] = $yt->channelUploads($channel);
                $results['YouTube'] = [(bool)$title, $title ? "Works. Found your channel: {$title}" : "The key works, but channel \"{$channel}\" wasn't found."];
            } else {
                $yt->channelUploads('YouTube');
                $results['YouTube'] = [true, 'Works. (Add your channel ID or handle so the scoreboard can find your uploads.)'];
            }
        } catch (Throwable $e) {
            $results['YouTube'] = [false, $e->getMessage()];
        }

        try {
            $reply = $llm->chat('Reply with the single word OK.', 'Say OK.', 60);
            $results['AI'] = [true, 'Works with ' . Setting::get('llm_model') . '. It said: "' . mb_substr($reply, 0, 40) . '"'];
        } catch (Throwable $e) {
            $results['AI'] = [false, $e->getMessage()];
        }

        try {
            app(\App\Services\Dictation::class)->token();
            $results['Dictation (AssemblyAI)'] = [true, 'Works. Live transcription is ready.'];
        } catch (Throwable $e) {
            $results['Dictation (AssemblyAI)'] = [false, $e->getMessage()];
        }

        return back()->with('checks', $results);
    }
}
