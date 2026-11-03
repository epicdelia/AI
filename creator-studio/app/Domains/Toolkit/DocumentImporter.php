<?php

namespace App\Domains\Toolkit;

use App\Domains\Toolkit\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * Turn an uploaded file into a Library document (and so into Memory, which indexes
 * documents on save). Ported from MagicAI's ChatPdfController::uploadPDF (smalot/pdfparser),
 * extended to Word, text/Markdown and subtitle files.
 *
 * Unlike the original, the file itself isn't kept: MagicAI copied every PDF to the public
 * disk; here only the extracted text is stored.
 */
class DocumentImporter
{
    public const TYPES = ['pdf', 'docx', 'txt', 'md', 'markdown', 'csv', 'srt', 'vtt'];

    public const MAX_MB = 20;

    public const MAX_CHARS = 400000; // ~60k words; longer files are truncated with a note

    public function import(UploadedFile $file, ?string $title = null): Document
    {
        $ext = Str::lower($file->getClientOriginalExtension());
        if (!in_array($ext, self::TYPES, true)) {
            throw new RuntimeException('Unsupported file. Upload PDF, Word (.docx), text, Markdown, CSV or subtitles (.srt/.vtt).');
        }

        $text = match ($ext) {
            'pdf' => $this->pdf($file->getRealPath()),
            'docx' => $this->docx($file->getRealPath()),
            'srt', 'vtt' => self::subtitles((string)file_get_contents($file->getRealPath())),
            default => (string)file_get_contents($file->getRealPath()),
        };

        $text = self::clean($text);
        if (mb_strlen($text) < 20) {
            throw new RuntimeException($ext === 'pdf'
                ? 'No text found in that PDF. It may be a scan (images only); export it with selectable text and try again.'
                : 'That file has no readable text.');
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS) . "\n\n[Truncated: the original was longer than " . number_format(self::MAX_CHARS) . ' characters.]';
        }

        $name = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'Uploaded document';

        return Document::create([
            'title' => mb_substr($title ?: Str::headline($name), 0, 200),
            'tool' => 'upload:' . $ext,
            'content' => $text,
            'inputs' => [
                'file' => mb_substr($file->getClientOriginalName(), 0, 200),
                'bytes' => $file->getSize(),
            ],
        ]);
    }

    /**
     * SRT/VTT → plain lines, without cue numbers, timings or styling.
     */
    public static function subtitles(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'WEBVTT' || ctype_digit($line) || str_contains($line, '-->')
                || preg_match('/^(NOTE|STYLE|REGION)\b/', $line)) {
                continue;
            }
            $line = trim(strip_tags($line));
            if ($line !== '' && end($lines) !== $line) { // auto-captions repeat the rolling line
                $lines[] = $line;
            }
        }

        return implode(' ', $lines);
    }

    /**
     * smalot/pdfparser, as in MagicAI: parse the file, take the text of every page.
     */
    private function pdf(string $path): string
    {
        try {
            $pdf = (new Parser())->parseFile($path);
            $pages = array_map(fn ($p) => trim($p->getText()), $pdf->getPages());
        } catch (Throwable $e) {
            throw new RuntimeException("Couldn't read that PDF: " . mb_substr($e->getMessage(), 0, 150));
        }

        return implode("\n\n", array_filter($pages));
    }

    /**
     * A .docx is a zip; the body text lives in word/document.xml.
     */
    private function docx(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Reading Word files needs PHP’s zip extension. Enable extension=zip in php.ini, or save the file as PDF.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            throw new RuntimeException("Couldn't open that Word file.");
        }
        $zip->close();

        // Paragraph and line breaks first, then drop the remaining tags.
        $xml = preg_replace(['~</w:p>~', '~<w:br[^>]*/>~', '~<w:tab[^>]*/>~'], ["\n\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function clean(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8'); // as MagicAI did: drop invalid bytes
        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
