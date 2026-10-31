<?php

namespace App\Domains\Engine\Stream;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Server-Sent Events transport, ported from MagicAI's Stream\StreamService.
 *
 * Protocol (same event names as MagicAI's chat stream):
 *   event: message  data: <id>            optional, once, before any text
 *   event: data     data: <json string>   one chunk of text
 *   event: error    data: <json string>   the request failed; the stream ends after it
 *   event: done     data: <json object>   final payload (saved records etc.)
 *   event: stop     data: [DONE]          always last
 * Text chunks are JSON-encoded so newlines and Markdown survive intact (MagicAI swapped
 * newlines for <br/>, which breaks Markdown rendering on the client).
 */
class StreamService
{
    public function prepareStreamEnvironment(): void
    {
        // Under PHPUnit the outer buffer belongs to the test runner (streamedContent() reads it).
        if (app()->runningUnitTests()) {
            return;
        }

        if (function_exists('ini_set')) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
        }

        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(true);
        }

        $minLevel = config('octane.enabled') ? 1 : 0;
        while (ob_get_level() > $minLevel) {
            if (! @ob_end_flush()) {
                break;
            }
        }
    }

    public function safeFlush(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        if (function_exists('flush')) {
            @flush();
        }
    }

    public function emit(string $event, string $data): void
    {
        echo "event: {$event}\n";
        foreach (explode("\n", $data) as $line) {
            echo "data: {$line}\n";
        }
        echo "\n";
        $this->safeFlush();
    }

    public function chunk(string $text): void
    {
        if ($text !== '') {
            $this->emit('data', json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public function aborted(): bool
    {
        return connection_aborted() === 1;
    }

    /**
     * Run $work inside an SSE response. $work receives this service (call ->chunk() as text
     * arrives) and returns the final payload for the "done" event. Exceptions become an
     * "error" event, so the client always gets a clean end of stream.
     *
     * @param  callable(self): (array|null)  $work
     */
    public function respond(callable $work, int $timeLimit = 300): StreamedResponse
    {
        $this->prepareStreamEnvironment();

        return response()->stream(function () use ($work, $timeLimit) {
            @set_time_limit($timeLimit);
            ignore_user_abort(false);
            // Some proxies hold back the first bytes until a buffer fills; a comment line opens the pipe.
            echo ': '.str_repeat(' ', 2048)."\n\n";
            $this->safeFlush();

            try {
                $payload = $work($this);
                $this->emit('done', json_encode($payload ?? new \stdClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (Throwable $e) {
                report($e);
                $this->emit('error', json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
            }

            $this->emit('stop', '[DONE]');
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream',
        ]);
    }
}
