<?php

namespace App\Domains\Engagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One automation: when a comment or DM matches, draft or send a reply (and, on Instagram, a DM).
 */
class EngagementRule extends Model
{
    public const TRIGGERS = [
        'keyword' => 'Comment or DM contains a keyword',
        'question' => 'Someone asks a question',
        'any_comment' => 'Any new comment',
        'any_dm' => 'Any new DM',
    ];

    protected $fillable = [
        'name', 'platforms', 'trigger', 'keywords', 'reply_mode', 'reply_template', 'instructions', 'dm_text',
        'auto_send', 'active', 'max_per_hour', 'fired',
    ];

    public function matches(EngagementItem $item): bool
    {
        if (!$this->active || !in_array($item->platform, $this->platforms ?? [], true)) {
            return false;
        }
        $text = mb_strtolower($item->text);

        return match ($this->trigger) {
            'keyword' => collect($this->keywords ?? [])->contains(fn ($k) => ($k = mb_strtolower(trim((string)$k))) !== ''
                && preg_match('/(^|[^\p{L}\p{N}])' . preg_quote($k, '/') . '($|[^\p{L}\p{N}])/u', $text)),
            'question' => str_contains($text, '?') || $item->intent === 'question',
            'any_comment' => $item->kind === 'comment',
            'any_dm' => $item->kind === 'dm',
            default => false,
        };
    }

    /**
     * {name} → the person's handle.
     */
    public function fillTemplate(?string $template, EngagementItem $item): string
    {
        return trim(str_replace('{name}', $item->author ? '@' . ltrim($item->author, '@') : '', (string)$template));
    }

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'keywords' => 'array',
            'auto_send' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
