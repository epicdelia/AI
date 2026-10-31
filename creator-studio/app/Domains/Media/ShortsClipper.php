<?php

namespace App\Domains\Media;

use App\Domains\Usage\AiUsage;
use App\Models\Setting;
use App\Services\YouTubeTranscript;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cut Shorts from a long-form video with Vizard. Ported from MagicAI's Packages\Vizard
 * (VizardService::submitVideoForClipping → project/create, retrieveClips →
 * project/query/{id}; BaseApiClient's VIZARDAI_API_KEY header; the VideoType / Ratio /
 * VideoLength enums). MagicAI's controller for it is a marketplace extension that isn't in
 * the codebase, so polling and result handling follow Vizard's API docs: code 2000 = done
 * with `videos`, 1000 = still processing, anything else = an error.
 */
class ShortsClipper
{
    public const BASE = 'https://elb-api.vizard.ai/hvizard-server-front/open-api/v1';

    // VideoType enum (subset)
    public const TYPE_REMOTE_FILE = 1;
    public const TYPE_YOUTUBE = 2;

    // VideoLength enum: 0 auto, 1 <30s, 2 30-60s, 3 60-90s, 4 90s-3min
    public const LENGTHS = [0, 1, 2, 3, 4];

    // Ratio enum: 1 = 9:16, 2 = 1:1, 3 = 4:5, 4 = 16:9
    public const RATIOS = [1, 2, 3, 4];

    /** @var callable|null test hook: fn(string $method, string $path, array $params): array (the JSON body) */
    public static $fake = null;

    public static function available(): bool
    {
        return self::$fake !== null || Setting::has('vizard_api_key');
    }

    /** @param array{lang?: string, length?: int, ratio?: int, max?: int, subtitles?: bool, headline?: bool} $o */
    public function submit(string $url, array $o = [], ?int $scriptId = null): ClipProject
    {
        if (! self::available()) {
            throw new RuntimeException('Cutting Shorts needs a Vizard API key in Settings.');
        }
        $url = trim($url);
        $isYouTube = YouTubeTranscript::videoId($url) !== null;
        if (! $isYouTube && ! preg_match('~^https?://\S+\.(mp4|mov|m4v|webm)(\?\S*)?$~i', $url)) {
            throw new RuntimeException('Use a YouTube link or a direct link to an .mp4/.mov file.');
        }

        $params = [
            'videoUrl' => $url,
            'videoType' => $isYouTube ? self::TYPE_YOUTUBE : self::TYPE_REMOTE_FILE,
            'lang' => $o['lang'] ?? 'en',
            'preferLength' => [in_array($o['length'] ?? 0, self::LENGTHS, true) ? (int) ($o['length'] ?? 0) : 0],
            'ratioOfClip' => in_array($o['ratio'] ?? 1, self::RATIOS, true) ? (int) ($o['ratio'] ?? 1) : 1,
            'subtitleSwitch' => ($o['subtitles'] ?? true) ? 1 : 0,
            'headlineSwitch' => ($o['headline'] ?? true) ? 1 : 0,
            'maxClipNumber' => max(1, min(20, (int) ($o['max'] ?? 6))),
        ];
        if (! $isYouTube) {
            $params['ext'] = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        }

        $body = $this->call('post', 'project/create', $params);
        $projectId = $body['projectId'] ?? null;
        if ((int) ($body['code'] ?? 0) !== 2000 || ! $projectId) {
            throw new RuntimeException('Vizard refused the video: '.($body['errMsg'] ?? $body['message'] ?? 'code '.($body['code'] ?? '?')));
        }

        return ClipProject::create([
            'script_id' => $scriptId, 'source_url' => $url, 'project_id' => (string) $projectId,
            'status' => 'processing', 'options' => $params,
        ]);
    }

    /** Ask Vizard for progress; stores clips when done. */
    public function refresh(ClipProject $project): ClipProject
    {
        if ($project->status !== 'processing') {
            return $project;
        }
        try {
            $body = $this->call('get', "project/query/{$project->project_id}");
        } catch (Throwable $e) {
            $project->update(['checked_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return $project;
        }

        $code = (int) ($body['code'] ?? 0);
        if ($code === 2000) {
            $clips = collect($body['videos'] ?? [])->map(fn ($v) => [
                'id' => (string) ($v['videoId'] ?? ''),
                'title' => (string) ($v['title'] ?? 'Clip'),
                'url' => $v['videoUrl'] ?? null,
                'seconds' => isset($v['videoMsDuration']) ? round($v['videoMsDuration'] / 1000) : null,
                'viral_score' => isset($v['viralScore']) ? (float) $v['viralScore'] : null,
                'viral_reason' => $v['viralReason'] ?? null,
                'transcript' => isset($v['transcript']) ? mb_substr((string) $v['transcript'], 0, 2000) : null,
                'editor_url' => $v['clipEditorUrl'] ?? null,
            ])->sortByDesc('viral_score')->values()->all();
            $project->update(['status' => 'done', 'clips' => $clips, 'error' => null, 'checked_at' => now()]);
        } elseif ($code === 1000) {
            $project->update(['checked_at' => now(), 'error' => null]);
        } else {
            $project->update(['status' => 'failed', 'error' => mb_substr((string) ($body['errMsg'] ?? 'Vizard error code '.$code), 0, 300), 'checked_at' => now()]);
        }

        return $project->fresh();
    }

    private function call(string $method, string $path, array $params = []): array
    {
        if (self::$fake) {
            return (self::$fake)($method, $path, $params);
        }
        $started = microtime(true);
        $http = Http::timeout(60)->connectTimeout(10)->withHeaders([
            'VIZARDAI_API_KEY' => (string) Setting::get('vizard_api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        /** @var Response $resp */
        $resp = $method === 'post' ? $http->post(self::BASE.'/'.$path, $params) : $http->get(self::BASE.'/'.$path);
        if ($path === 'project/create') {
            try {
                AiUsage::create(['feature' => 'shorts', 'engine' => 'vizard', 'model' => 'clipping', 'unit' => 'job', 'quantity' => 1,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'ok' => $resp->successful()]);
            } catch (Throwable) {
            }
        }
        if ($resp->failed()) {
            throw new RuntimeException("Vizard request failed ({$resp->status()}).");
        }

        return $resp->json() ?? [];
    }
}
