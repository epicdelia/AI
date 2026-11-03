<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flow dictation backend (port of app.py): mints short-lived AssemblyAI streaming tokens so the
 * API key never reaches the browser, and holds the cleanup prompts used to polish a transcript.
 */
class Dictation
{
    public const TOKEN_URL = 'https://streaming.assemblyai.com/v3/token';
    public const STREAM_URL = 'wss://streaming.assemblyai.com/v3/ws';

    public const CLEANUP_RULES = <<<'TXT'
You turn raw dictated speech into clean written text.
- Remove filler words (um, uh, like, you know), stutters, repeated words and false starts.
- If the speaker corrects themselves ("Tuesday, no, Wednesday"), keep only the correction.
- Fix punctuation, capitalization and run-on sentences.
- Keep the speaker's meaning, facts, names, numbers and tone. Do not add new ideas or details.
- Do not guess at words that look misheard; keep them as spoken.
- Write in the same language the speaker used; do not translate.
- Follow spoken structure: when the speaker lists things ("first... second...", "number one...",
  "a few things: ..."), format them as a list; obey spoken commands like "new paragraph",
  "new line", "bullet point" or "full stop" instead of writing those words out.
- Write numbers, dates, times, money and percentages the way people write them (3pm, $40, 25%).

TXT;

    public const STYLES = [
        'auto' => "- Format as clean Markdown: a short bolded title line, then either a polished paragraph,\n  bullet points, or a short email, whichever fits what was said. Use headers only if there\n  are clearly separate topics.",
        'message' => "- Format as a chat message (Slack, WhatsApp, iMessage): plain conversational text, no title,\n  no headers, short paragraphs. Use bullets only if the speaker lists several items.",
        'email' => "- Format as an email body: a greeting line if the speaker named who it is for, short paragraphs,\n  then a simple sign-off such as \"Thanks,\". Do not invent a name, subject line or details.",
        'notes' => "- Format as notes: a short bolded title line, then concise bullet points, one idea per bullet,\n  with action items marked \"- [ ]\".",
        'idea' => "- Format as a YouTube video idea: a bolded working title under 60 characters, then\n  **Premise:** one sentence, **Angle:** why she's the right person, and bullet points for\n  everything else she said.",
    ];

    public const REWRITES = [
        'shorter' => 'Make it about half as long. Keep every fact, name, number and action item.',
        'formal' => 'Make the tone more formal and professional.',
        'friendly' => 'Make the tone warmer and more casual, like a message to a colleague you like.',
        'grammar' => 'Only fix grammar, spelling and punctuation. Change nothing else.',
    ];

    public static function systemPrompt(string $style = 'auto'): string
    {
        return self::CLEANUP_RULES . (self::STYLES[$style] ?? self::STYLES['auto']) . "\nReturn only the text, with no preamble.";
    }

    public static function rewritePrompt(string $action): string
    {
        return "You edit a short piece of writing.\n- " . self::REWRITES[$action] . "\n"
            . "- Keep the same language and the same Markdown formatting style.\n"
            . "- Do not add new ideas, facts or details.\n"
            . 'Return only the rewritten text, with no preamble.';
    }

    public function token(): array
    {
        $key = Setting::get('assemblyai_api_key');
        if (!$key) {
            throw new RuntimeException('No AssemblyAI key yet. Add one under Settings to use dictation.');
        }
        $resp = Http::timeout(15)->withHeaders([
            'Authorization' => $key,
        ])
            ->get(self::TOKEN_URL, [
                'expires_in_seconds' => 60,
            ]);
        if ($resp->failed()) {
            throw new RuntimeException("Streaming token request failed ({$resp->status()}): " . mb_substr($resp->body(), 0, 300));
        }

        return [
            'token' => $resp->json('token'),
            'stream_url' => self::STREAM_URL,
        ];
    }
}
