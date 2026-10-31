<?php

namespace App\Http\Controllers\Api;

use App\Domains\Chat\Models\Conversation;
use App\Domains\Toolkit\DocxWriter;
use App\Domains\Toolkit\Models\Document;
use App\Http\Controllers\Controller;
use App\Models\Script;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Downloads as Word / Markdown / text, ported from MagicAI's ExportChatController
 * (generateWord / generateTxt; generatePdf is the browser's print here). Extended from chats
 * to scripts (with chosen sections) and Library documents.
 */
class ExportController extends Controller
{
    private const SCRIPT_PARTS = [
        'body' => 'Script', 'outline' => 'Plan', 'review' => 'AI review', 'fact_check' => 'Fact-check',
        'transcript' => 'Recording transcript', 'substack' => 'Substack post', 'edit_brief' => 'Editor brief', 'publish_kit' => 'Publish kit',
    ];

    public function script(Request $request, Script $script): Response
    {
        $parts = array_intersect(array_keys(self::SCRIPT_PARTS), explode(',', (string) $request->query('parts', 'body')));
        $md = [];
        foreach ($parts as $part) {
            $value = $script->{$part};
            if ($part === 'outline' && is_array($value)) {
                $value = collect($value['chapters'] ?? [])->map(fn ($c, $i) => ($i + 1).". **{$c['title']}**".(! empty($c['beat']) ? " · {$c['beat']}" : ''))->implode("\n");
            }
            if (trim((string) $value) === '') {
                continue;
            }
            $md[] = (count($parts) > 1 ? '# '.self::SCRIPT_PARTS[$part]."\n\n" : '').trim((string) $value);
        }

        return $this->respond($request, $script->title, implode("\n\n---\n\n", $md) ?: '(empty)');
    }

    public function chat(Request $request, Conversation $conversation): Response
    {
        $name = \App\Models\Setting::get('creator_name') ?: (auth()->user()?->name ?: 'You');
        $md = $conversation->messages->map(fn ($m) => ($m->role === 'user' ? "**{$name}:**" : '**Producer:**')."\n\n".trim($m->content))->implode("\n\n---\n\n");

        return $this->respond($request, $conversation->title, $md ?: '(no messages)');
    }

    public function document(Request $request, Document $document): Response
    {
        return $this->respond($request, $document->title, $document->content);
    }

    private function respond(Request $request, string $title, string $markdown): Response
    {
        $format = in_array($request->query('format'), ['docx', 'md', 'txt'], true) ? $request->query('format') : 'docx';
        $file = (Str::slug($title) ?: 'export').'.'.$format;

        try {
            [$bytes, $mime] = match ($format) {
                'docx' => [DocxWriter::fromMarkdown($title, $markdown), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'md' => ["# {$title}\n\n{$markdown}\n", 'text/markdown; charset=utf-8'],
                'txt' => [$title."\n".str_repeat('=', mb_strlen($title))."\n\n".self::plain($markdown)."\n", 'text/plain; charset=utf-8'],
            };
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Markdown → readable plain text (MagicAI's generateTxt stripped tags the same way). */
    private static function plain(string $md): string
    {
        $t = preg_replace('/^#{1,6}\s+(.*)$/m', "$1\n".'', $md);
        $t = preg_replace('/\*\*([^*]+)\*\*|__([^_]+)__/', '$1$2', $t);
        $t = preg_replace('/(?<![*\w])\*([^*\n]+)\*/', '$1', $t);
        $t = preg_replace('/\[([^\]]+)\]\((https?:[^)]+)\)/', '$1 ($2)', $t);

        return trim(str_replace(['`', "\n---\n"], ['', "\n\n"], $t));
    }
}
