<?php

namespace App\Domains\Media;

use App\Domains\Usage\AiUsage;
use App\Domains\Usage\UsageLimit;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Short b-roll clips for a script's [B-ROLL] notes. Ported from MagicAI's Packages\FalAI
 * (FalAIService on queue.fal.run; KlingV3 submit → requests/{id}/status → requests/{id};
 * Veo31 endpoints), using fal's returned status_url/response_url when present and the
 * MagicAI path rule otherwise. Optionally starts from one of her images (image-to-video).
 */
class BrollGenerator
{
    public const QUEUE = 'https://queue.fal.run';

    /** model => [label, text-to-video endpoint, image-to-video endpoint, app path for requests/, durations, default duration] */
    public const MODELS = [
        'kling-v3-standard' => ['Kling 3 Standard', 'fal-ai/kling-video/v3/standard/text-to-video', 'fal-ai/kling-video/v3/standard/image-to-video', 'fal-ai/kling-video', ['5', '10'], '5'],
        'kling-v3-pro' => ['Kling 3 Pro', 'fal-ai/kling-video/v3/pro/text-to-video', 'fal-ai/kling-video/v3/pro/image-to-video', 'fal-ai/kling-video', ['5', '10'], '5'],
        'veo3.1-fast' => ['Veo 3.1 Fast', 'fal-ai/veo3.1/fast', 'fal-ai/veo3.1/fast/image-to-video', 'fal-ai/veo3.1', ['4s', '6s', '8s'], '6s'],
    ];

    public const ASPECTS = ['16:9', '9:16', '1:1'];

    /** @var callable|null test hook: fn(string $method, string $url, array $body): array */
    public static $fake = null;

    public static function available(): bool
    {
        return self::$fake !== null || Setting::has('fal_api_key');
    }

    public function submit(string $prompt, array $o = []): VideoJob
    {
        if (! self::available()) {
            throw new RuntimeException('B-roll clips need a fal.ai key in Settings.');
        }
        $model = isset(self::MODELS[$o['model'] ?? '']) ? $o['model'] : 'kling-v3-standard';
        [, $t2v, $i2v, , $durations, $defaultDuration] = self::MODELS[$model];
        UsageLimit::ensure('video', 1);

        $image = null;
        if (! empty($o['media_id'])) {
            $m = MediaFile::find($o['media_id']);
            if (! $m || ! in_array($m->kind, ['thumbnail', 'photo'], true)) {
                throw new RuntimeException('Pick one of your mockups or photos as the first frame.');
            }
            $image = "data:{$m->mime};base64,".base64_encode((string) $m->contents()); // fal accepts data URIs for image_url
        }

        $params = [
            'prompt' => mb_substr(trim($prompt), 0, 2000),
            'duration' => in_array($o['duration'] ?? null, $durations, true) ? $o['duration'] : $defaultDuration,
            'aspect_ratio' => in_array($o['aspect'] ?? null, self::ASPECTS, true) ? $o['aspect'] : '16:9',
        ];
        if (str_starts_with($model, 'veo')) {
            $params['generate_audio'] = false; // b-roll sits under her voice
            $params['resolution'] = '720p';
        } else {
            $params['negative_prompt'] = 'blur, distort, low quality, text, watermark';
        }
        if ($image) {
            $params['image_url'] = $image;
        }

        $body = $this->call('post', self::QUEUE.'/'.($image ? $i2v : $t2v), $params);
        $requestId = $body['request_id'] ?? null;
        if (! $requestId) {
            throw new RuntimeException('fal.ai didn’t accept the job: '.mb_substr(json_encode($body['detail'] ?? $body), 0, 200));
        }
        $this->record($model);

        return VideoJob::create([
            'script_id' => $o['script_id'] ?? null, 'model' => $model, 'prompt' => $params['prompt'],
            'params' => array_diff_key($params, ['image_url' => 1]) + ['from_image' => (bool) $image],
            'request_id' => $requestId, 'status_url' => $body['status_url'] ?? null, 'response_url' => $body['response_url'] ?? null,
            'status' => 'queued',
        ]);
    }

    /** Poll fal; when complete, download the video into private media. */
    public function refresh(VideoJob $job): VideoJob
    {
        if (! in_array($job->status, ['queued', 'running'], true)) {
            return $job;
        }
        $app = self::MODELS[$job->model][3] ?? 'fal-ai/kling-video';
        $statusUrl = $job->status_url ?: self::QUEUE."/{$app}/requests/{$job->request_id}/status";
        $resultUrl = $job->response_url ?: self::QUEUE."/{$app}/requests/{$job->request_id}";

        try {
            $st = $this->call('get', $statusUrl);
            $status = strtoupper((string) ($st['status'] ?? ''));
            if ($status === 'IN_QUEUE' || $status === 'IN_PROGRESS') {
                $job->update(['status' => $status === 'IN_QUEUE' ? 'queued' : 'running', 'checked_at' => now()]);

                return $job;
            }
            if ($status !== 'COMPLETED') {
                throw new RuntimeException('fal.ai status: '.($st['status'] ?? 'unknown'));
            }

            $res = $this->call('get', $resultUrl);
            $url = $res['video']['url'] ?? ($res['videos'][0]['url'] ?? null);
            if (! $url) {
                throw new RuntimeException(isset($res['detail']) ? 'fal.ai: '.mb_substr(json_encode($res['detail']), 0, 200) : 'fal.ai finished but returned no video.');
            }
            $bytes = self::$fake ? 'FAKEMP4' : Http::timeout(180)->get($url)->throw()->body();
            $media = MediaFile::store('broll', $bytes, 'mp4', 'video/mp4', [
                'script_id' => $job->script_id, 'title' => mb_strimwidth($job->prompt, 0, 80, '…'),
                'provider' => 'fal', 'model' => $job->model, 'prompt' => $job->prompt, 'meta' => $job->params,
            ]);
            $job->update(['status' => 'done', 'media_file_id' => $media->id, 'error' => null, 'checked_at' => now()]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300), 'checked_at' => now()]);
        }

        return $job->fresh('media');
    }

    private function call(string $method, string $url, array $body = []): array
    {
        if (self::$fake) {
            return (self::$fake)($method, $url, $body);
        }
        $http = Http::timeout(60)->connectTimeout(10)->withHeaders(['Authorization' => 'Key '.Setting::get('fal_api_key')]);
        $resp = $method === 'post' ? $http->post($url, $body) : $http->get($url);
        if ($resp->failed() && $resp->status() !== 202) {
            throw new RuntimeException("fal.ai request failed ({$resp->status()}): ".mb_substr($resp->body(), 0, 200));
        }

        return $resp->json() ?? [];
    }

    private function record(string $model): void
    {
        try {
            AiUsage::create(['feature' => 'broll', 'engine' => 'fal', 'model' => $model, 'unit' => 'video', 'quantity' => 1]);
        } catch (Throwable) {
        }
    }
}
