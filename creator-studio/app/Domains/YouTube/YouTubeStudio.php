<?php

namespace App\Domains\YouTube;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Media\MediaFile;
use App\Domains\Social\Connect\ConnectException;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Uploading to YouTube and title tests, through her connected Google account (Connections →
 * YouTube; the youtube.force-ssl permission covers both). Uploads are resumable: each run sends
 * as much as fits in its time budget and the next run carries on from YouTube's own offset.
 */
class YouTubeStudio
{
    /** YouTube wants chunks in multiples of 256 KB. */
    public const CHUNK = 8 * 256 * 1024 * 4; // 8 MB

    private const API = 'https://www.googleapis.com';

    public static function token(): string
    {
        $account = SocialAccount::where('platform', 'youtube')->where('status', 'connected')->first()
            ?? throw new ConnectException('Connect YouTube first (Connections → YouTube).');

        return Connections::token($account);
    }

    // ------------------------------------------------------------ uploads

    /** Send queued uploads for up to $seconds. Returns how many finished. */
    public static function drainUploads(int $seconds = 50): int
    {
        $deadline = microtime(true) + $seconds;
        $done = 0;
        foreach (VideoUpload::whereIn('status', ['queued', 'uploading'])->orderBy('id')->get() as $u) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                if (self::push($u, $deadline)) {
                    $done++;
                }
            } catch (Throwable $e) {
                $u->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
                AgentEvent::log('failed', "YouTube upload “{$u->title}” stopped: {$e->getMessage()}", null, null, 'youtube');
            }
        }

        return $done;
    }

    /** True when this upload finished. */
    public static function push(VideoUpload $u, float $deadline): bool
    {
        $token = self::token();
        if (! $u->upload_url) {
            $status = $u->privacy === 'scheduled' && $u->publish_at
                ? ['privacyStatus' => 'private', 'publishAt' => $u->publish_at->toIso8601ZuluString(), 'selfDeclaredMadeForKids' => false]
                : ['privacyStatus' => in_array($u->privacy, ['private', 'unlisted', 'public'], true) ? $u->privacy : 'private', 'selfDeclaredMadeForKids' => false];
            $r = Http::withToken($token)->timeout(30)->withHeaders([
                'X-Upload-Content-Length' => (string) $u->bytes, 'X-Upload-Content-Type' => $u->mime ?: 'video/mp4',
            ])->post(self::API.'/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => array_filter(['title' => mb_substr($u->title, 0, 100), 'description' => mb_substr((string) $u->description, 0, 5000),
                    'tags' => $u->tags ?: null, 'categoryId' => '28']), // Science & Technology
                'status' => $status,
            ]);
            if (! $r->successful() || ! $r->header('Location')) {
                throw new RuntimeException('YouTube didn’t accept the upload: '.($r->json('error.message') ?? 'HTTP '.$r->status()));
            }
            $u->update(['upload_url' => $r->header('Location'), 'status' => 'uploading']);
        }

        $disk = Storage::disk('local');
        $stream = $disk->readStream($u->path);
        try {
            while (microtime(true) < $deadline) {
                $start = (int) $u->sent;
                fseek($stream, $start);
                $data = fread($stream, self::CHUNK);
                $end = $start + strlen($data) - 1;
                $r = Http::withToken($token)->timeout(120)->withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$u->bytes}"])
                    ->withBody($data, $u->mime ?: 'video/mp4')->put($u->upload_url);
                if ($r->status() === 308) {
                    // "Resume incomplete": Range says what YouTube has.
                    $range = (string) $r->header('Range');
                    $u->update(['sent' => preg_match('/bytes=0-(\d+)/', $range, $m) ? (int) $m[1] + 1 : $start]);

                    continue;
                }
                if ($r->successful()) {
                    $u->update(['sent' => $u->bytes, 'status' => 'done', 'video_id' => $r->json('id'), 'error' => null]);
                    self::setThumbnail($u, $token);
                    $disk->delete($u->path); // YouTube has it now
                    AgentEvent::log('published', "“{$u->title}” is on YouTube".($u->privacy === 'scheduled' && $u->publish_at ? ' (goes live '.$u->publish_at->format('D j M, H:i').')' : '').'.', null, null, 'youtube');

                    return true;
                }
                if ($r->status() === 404 || $r->status() === 410) {
                    $u->update(['upload_url' => null, 'sent' => 0]); // session expired: start a fresh one next run

                    return false;
                }
                if ($r->status() >= 500) {
                    return false; // try again next run
                }
                throw new RuntimeException('YouTube refused the upload: '.($r->json('error.message') ?? 'HTTP '.$r->status()));
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return false;
    }

    private static function setThumbnail(VideoUpload $u, string $token): void
    {
        $m = $u->thumbnail_media_id ? MediaFile::find($u->thumbnail_media_id) : null;
        if (! $m || ! ($bytes = $m->contents())) {
            return;
        }
        $r = Http::withToken($token)->timeout(60)->withBody($bytes, $m->mime)
            ->post(self::API."/upload/youtube/v3/thumbnails/set?videoId={$u->video_id}&uploadType=media");
        if (! $r->successful()) {
            $u->update(['error' => 'Video uploaded; the thumbnail wasn’t accepted ('.($r->json('error.message') ?? 'HTTP '.$r->status()).'). Custom thumbnails need a verified YouTube account.']);
        }
    }

    // ------------------------------------------------------------ title tests

    /** [title, views, categoryId, snippet] for a video of hers. */
    public static function video(string $videoId, string $token): array
    {
        $r = Http::withToken($token)->timeout(20)->get(self::API.'/youtube/v3/videos', ['part' => 'snippet,statistics', 'id' => $videoId]);
        $v = $r->json('items.0') ?? throw new RuntimeException('That video wasn’t found on your channel.');

        return [$v['snippet']['title'], (int) ($v['statistics']['viewCount'] ?? 0), $v['snippet']];
    }

    public static function setTitle(string $videoId, string $title, string $token): void
    {
        [, , $snippet] = self::video($videoId, $token);
        $r = Http::withToken($token)->timeout(20)->put(self::API.'/youtube/v3/videos?part=snippet', [
            'id' => $videoId,
            'snippet' => array_filter(['title' => mb_substr($title, 0, 100), 'categoryId' => $snippet['categoryId'] ?? '28', 'description' => $snippet['description'] ?? '',
                'tags' => $snippet['tags'] ?? null, 'defaultLanguage' => $snippet['defaultLanguage'] ?? null], fn ($v) => $v !== null),
        ]);
        if (! $r->successful()) {
            throw new RuntimeException('YouTube didn’t accept the new title: '.($r->json('error.message') ?? 'HTTP '.$r->status()));
        }
    }

    public static function startTest(string $videoId, array $titles, int $hours, int $rounds): TitleTest
    {
        if (TitleTest::where('video_id', $videoId)->where('status', 'running')->exists()) {
            throw new RuntimeException('That video already has a test running.');
        }
        $token = self::token();
        [$original, $views] = self::video($videoId, $token);
        $titles = array_values(array_unique(array_filter(array_map(fn ($t) => trim((string) $t), array_merge([$original], $titles)))));
        if (count($titles) < 2) {
            throw new RuntimeException('Add at least one other title to test against the current one.');
        }
        $titles = array_slice($titles, 0, 3);

        return TitleTest::create(['video_id' => $videoId, 'original_title' => $original, 'titles' => $titles, 'windows' => [], 'current' => 0,
            'window_started_at' => now(), 'views_at_start' => $views, 'hours' => $hours, 'rounds' => $rounds, 'status' => 'running']);
    }

    /** Rotate tests whose window is over; finish the ones that have run every round. */
    public static function tickTests(): int
    {
        $n = 0;
        foreach (TitleTest::where('status', 'running')->get() as $t) {
            if ($t->window_started_at->addHours($t->hours)->isFuture()) {
                continue;
            }
            try {
                $token = self::token();
                [, $views] = self::video($t->video_id, $token);
                $windows = $t->windows ?? [];
                $windows[] = ['title_index' => $t->current, 'start' => $t->window_started_at->toIso8601String(), 'end' => now()->toIso8601String(),
                    'views' => max(0, $views - $t->views_at_start), 'hours' => round($t->window_started_at->diffInMinutes(now()) / 60, 2)];
                $total = count($t->titles) * $t->rounds;
                if (count($windows) >= $total) {
                    $rates = $t->rates($windows);
                    arsort($rates);
                    $winner = (int) array_key_first($rates);
                    self::setTitle($t->video_id, $t->titles[$winner], $token);
                    $t->update(['windows' => $windows, 'status' => 'done', 'winner' => $winner, 'current' => $winner]);
                    AgentEvent::log('learned', "Title test done: “{$t->titles[$winner]}” won (".round($rates[$winner], 1).' views/hour) and is now the title.', null, null, 'youtube');
                } else {
                    $next = ($t->current + 1) % count($t->titles);
                    self::setTitle($t->video_id, $t->titles[$next], $token);
                    $t->update(['windows' => $windows, 'current' => $next, 'window_started_at' => now(), 'views_at_start' => $views]);
                }
                $n++;
            } catch (Throwable $e) {
                $t->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 400)]);
            }
        }

        return $n;
    }
}
