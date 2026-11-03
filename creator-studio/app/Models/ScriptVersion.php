<?php

namespace App\Models;

use App\Domains\Copilot\ParagraphDiff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved copy of a script body. Autosave, AI rewrites and Copilot edits all overwrite
 * scripts.body, so the old text is kept here first: every 10 minutes while she types, and
 * always before a large change (a rewrite, a big paste or delete).
 */
class ScriptVersion extends Model
{
    public const KEEP = 60;           // newest automatic versions kept per script
    public const EVERY_MINUTES = 10;  // at most one timed snapshot per this many minutes

    protected $fillable = ['script_id', 'title', 'body', 'words', 'source', 'label'];

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * Called while a script is updating: keep the body it's about to lose, when it's worth keeping.
     */
    public static function beforeChange(Script $script): void
    {
        if (!$script->isDirty('body')) {
            return;
        }
        $old = (string)$script->getOriginal('body');
        if (trim($old) === '') {
            return;
        }

        $last = self::where('script_id', $script->id)->latest('id')->first();
        $large = self::isLarge($old, (string)$script->body);
        $due = !$last || $last->created_at->lte(now()->subMinutes(self::EVERY_MINUTES));
        if (!$large && !$due) {
            return;
        }

        self::keep($script, $old, (string)$script->getOriginal('title'), 'auto', $large ? 'Before a large edit' : null);
    }

    /**
     * Save a copy of the body (skipped when it matches the newest copy).
     */
    public static function keep(Script $script, ?string $body = null, ?string $title = null, string $source = 'manual', ?string $label = null): ?self
    {
        $body ??= (string)$script->body;
        $last = self::where('script_id', $script->id)->latest('id')->first();
        if (trim($body) === '') {
            return null;
        }
        if ($last && $last->body === $body) {
            // Same text as the newest copy: naming it by hand promotes that copy instead of duplicating it.
            if ($source === 'manual') {
                $last->update([
                    'source' => 'manual',
                    'label' => $label !== null ? mb_substr($label, 0, 120) : $last->label,
                ]);
            }

            return $source === 'manual' ? $last : null;
        }

        $version = self::create([
            'script_id' => $script->id,
            'title' => $title ?? $script->title,
            'body' => $body,
            'words' => str_word_count(strip_tags($body)),
            'source' => $source,
            'label' => $label !== null ? mb_substr($label, 0, 120) : null,
        ]);

        // Prune old automatic copies; versions she saved by hand stay.
        $stale = self::where('script_id', $script->id)->where('source', '!=', 'manual')
            ->orderByDesc('id')->skip(self::KEEP)->take(1000)->pluck('id');
        if ($stale->isNotEmpty()) {
            self::whereIn('id', $stale)->delete();
        }

        return $version;
    }

    /**
     * A rewrite, a big paste or a big delete: several paragraphs replaced, or the length jumps.
     */
    public static function isLarge(string $before, string $after): bool
    {
        $a = ParagraphDiff::split($before);
        $b = array_flip(ParagraphDiff::split($after));
        $gone = count(array_filter($a, fn ($p) => !isset($b[$p])));
        if ($gone >= 3 && $gone >= count($a) * 0.3) {
            return true;
        }
        $wa = str_word_count($before);
        $wb = str_word_count($after);

        return abs($wa - $wb) >= max(80, $wa * 0.25);
    }
}
