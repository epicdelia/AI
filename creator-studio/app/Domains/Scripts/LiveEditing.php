<?php

namespace App\Domains\Scripts;

use App\Models\Script;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Editing a script together (her and her team): each body change is a numbered revision
 * kept for a while; who has the script open (and on which line) is a presence row refreshed
 * by the editor's poll. A save based on an older revision is merged (Merge3) with what was
 * saved in between.
 */
class LiveEditing
{
    /**
     * Revisions kept per script, so a slow save can still be merged.
     */
    public const KEEP = 60;

    /**
     * Seconds a presence counts as "here".
     */
    public const PRESENCE_TTL = 25;

    public static function remember(Script $script): void
    {
        DB::table('script_revisions')->updateOrInsert(
            ['script_id' => $script->id, 'revision' => (int)$script->revision],
            ['body' => (string)$script->body, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()],
        );
        $cut = (int)$script->revision - self::KEEP;
        if ($cut > 0) {
            DB::table('script_revisions')->where('script_id', $script->id)->where('revision', '<', $cut)->delete();
        }
    }

    public static function bodyAt(Script $script, int $revision): ?string
    {
        $body = DB::table('script_revisions')->where('script_id', $script->id)->where('revision', $revision)->value('body');

        return $body === null ? null : (string)$body;
    }

    /**
     * Save $body that was written on top of $baseRevision: straight through when nothing
     * changed in between, else merged with the newer text.
     *
     * @return array{body: string, merged: bool, conflicts: int}
     */
    public static function resolve(Script $script, string $body, int $baseRevision): array
    {
        if ($baseRevision >= (int)$script->revision) {
            return ['body' => $body, 'merged' => false, 'conflicts' => 0];
        }
        $base = self::bodyAt($script, $baseRevision);
        $theirs = (string)$script->body;
        if ($base === null) {
            // Too old to merge safely: keep both, theirs first, and say so.
            return ['body' => $theirs === $body ? $body : $theirs . "\n\n" . $body, 'merged' => true, 'conflicts' => 1];
        }
        $r = Merge3::merge($base, $body, $theirs);

        return ['body' => $r['text'], 'merged' => true, 'conflicts' => $r['conflicts']];
    }

    public static function touch(Script $script, User $user, ?int $line): void
    {
        DB::table('script_presences')->updateOrInsert(
            ['script_id' => $script->id, 'user_id' => $user->id],
            ['cursor_line' => $line, 'seen_at' => now()],
        );
    }

    public static function leave(Script $script, User $user): void
    {
        DB::table('script_presences')->where('script_id', $script->id)->where('user_id', $user->id)->delete();
    }

    /**
     * @return array<int, array{user_id: int, name: string, line: ?int}>
     */
    public static function others(Script $script, User $me): array
    {
        return DB::table('script_presences')
            ->join('users', 'users.id', '=', 'script_presences.user_id')
            ->where('script_presences.script_id', $script->id)
            ->where('script_presences.user_id', '!=', $me->id)
            ->where('script_presences.seen_at', '>=', now()->subSeconds(self::PRESENCE_TTL))
            ->get(['users.id', 'users.name', 'script_presences.cursor_line'])
            ->map(fn ($r) => ['user_id' => (int)$r->id, 'name' => $r->name, 'line' => $r->cursor_line !== null ? (int)$r->cursor_line : null])
            ->all();
    }
}
