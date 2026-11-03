<?php

namespace App\Domains\Toolkit;

use RuntimeException;
use ZipArchive;

/**
 * Markdown → .docx, for exporting scripts, chats and Library documents. MagicAI's
 * ExportChatController used PhpWord (+ dompdf for PDF); this writes the handful of Office
 * Open XML parts directly, so no extra dependency. PDF is done by the browser's print.
 *
 * Supports headings (#..###), paragraphs, bullet and numbered items, quotes, **bold**,
 * *italic*, and highlights [VISUAL]/[B-ROLL] cues so an editor can spot them.
 */
class DocxWriter
{
    public static function fromMarkdown(string $title, string $markdown): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Word export needs PHP’s zip extension. Enable extension=zip in php.ini, or export as Markdown.');
        }

        $body = self::paragraph($title, 'Title');
        foreach (preg_split('/\R/u', str_replace("\r", '', $markdown)) as $line) {
            $t = rtrim($line);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.*)$/u', $t, $m)) {
                $body .= self::paragraph($m[2], 'Heading' . strlen($m[1]));
            } elseif (preg_match('/^\s*[-*+]\s+(?:\[( |x|X)\]\s+)?(.*)$/u', $t, $m)) {
                $box = $m[1] === '' ? '• ' : (trim($m[1]) === '' ? '☐ ' : '☑ ');
                $body .= self::paragraph($box . $m[2], 'ListParagraph');
            } elseif (preg_match('/^\s*(\d+)[.)]\s+(.*)$/u', $t, $m)) {
                $body .= self::paragraph("{$m[1]}. {$m[2]}", 'ListParagraph');
            } elseif (preg_match('/^>\s?(.*)$/u', $t, $m)) {
                $body .= self::paragraph($m[1], 'Quote');
            } elseif (preg_match('/^\s*([-*_])\s*\1\s*\1[\s\-*_]*$/', $t)) {
                $body .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="CCCCCC"/></w:pBdr></w:pPr></w:p>';
            } else {
                $body .= self::paragraph($t);
            }
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', self::styles());
        $zip->close();

        $bytes = (string)file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private static function paragraph(string $text, ?string $style = null): string
    {
        $ppr = $style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '';

        return '<w:p>' . $ppr . self::runs($text) . '</w:p>';
    }

    /**
     * **bold**, *italic* and [CUE] spans → runs.
     */
    private static function runs(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\((https?:[^)]+)\)/u', '$1 ($2)', $text); // links → "text (url)"
        $out = '';
        foreach (preg_split('/(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|\[(?:VISUAL|B-ROLL|TEXT|VERIFY|YOUR STORY)[^\]]*\])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            $rpr = '';
            if (preg_match('/^\*\*(.+)\*\*$/u', $part, $m)) {
                [$part, $rpr] = [$m[1], '<w:b/>'];
            } elseif (preg_match('/^\*(.+)\*$/u', $part, $m)) {
                [$part, $rpr] = [$m[1], '<w:i/>'];
            } elseif (str_starts_with($part, '[') && str_ends_with($part, ']')) {
                $rpr = '<w:b/><w:color w:val="7B5CD6"/><w:shd w:val="clear" w:color="auto" w:fill="EFEAFD"/>';
            }
            $part = str_replace('`', '', $part);
            $out .= '<w:r>' . ($rpr ? "<w:rPr>{$rpr}</w:rPr>" : '') . '<w:t xml:space="preserve">' . htmlspecialchars($part, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r>';
        }

        return $out;
    }

    private static function styles(): string
    {
        $h = fn (string $id, string $name, int $size, string $color = '2B2230') => '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/><w:spacing w:before="240" w:after="80"/></w:pPr><w:rPr><w:b/><w:color w:val="' . $color . '"/><w:sz w:val="' . $size . '"/></w:rPr></w:style>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . $h('Title', 'Title', 40, 'D8487E') . $h('Heading1', 'heading 1', 32) . $h('Heading2', 'heading 2', 28) . $h('Heading3', 'heading 3', 24)
            . '<w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="360"/><w:spacing w:after="60"/></w:pPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="567"/></w:pPr><w:rPr><w:i/><w:color w:val="7F7485"/></w:rPr></w:style>'
            . '</w:styles>';
    }
}
