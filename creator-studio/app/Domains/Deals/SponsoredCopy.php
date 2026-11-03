<?php

namespace App\Domains\Deals;

use App\Domains\Clients\ClientNotifier;
use App\Domains\Social\Models\SocialPost;
use App\Models\Deal;
use App\Models\Script;

/**
 * The client signs off on the exact sponsored words before they go out: the linked social
 * post, or the script's sponsor section. While approval is pending (or changes were asked
 * for) the linked post can't be scheduled or published, unless she overrides it.
 */
class SponsoredCopy
{
    public const DUE_DAYS = 3;

    public static function draftFor(Deal $deal): string
    {
        if ($deal->social_post_id && ($p = SocialPost::find($deal->social_post_id))) {
            return trim((string)$p->body);
        }
        if ($deal->script_id && ($s = Script::find($deal->script_id))) {
            // The "## Sponsor…" section of the script, else nothing.
            if (preg_match('/^##+\s*[^\n]*sponsor[^\n]*\n(.*?)(?=^##\s|\z)/ims', (string)$s->body, $m)) {
                return trim($m[1]);
            }
        }

        return '';
    }

    public static function send(Deal $deal, string $text, ?string $dueOn = null): void
    {
        abort_if(trim($text) === '', 422, 'Write the copy first.');
        abort_unless($deal->user_id, 422, 'Link this deal to the client’s account first (Invite to dashboard).');
        $deal->forceFill([
            'copy_text' => trim($text),
            'copy_status' => 'pending',
            'copy_note' => null,
            'copy_sent_at' => now(),
            'copy_approved_at' => null,
            'copy_due_on' => $dueOn ?? now()->addDays(self::DUE_DAYS)->toDateString(),
            'copy_reminders' => 0,
            'copy_override' => false,
        ])->saveQuietly();
        ClientNotifier::event($deal, 'copy', 'Sponsored copy sent for approval');
        ClientNotifier::email($deal, "Please approve the sponsored copy: {$deal->brand_name}",
            'The exact wording for the sponsored content is ready. Approve it or ask for changes by ' . \Carbon\CarbonImmutable::parse($deal->copy_due_on)->toFormattedDateString() . '.');
    }

    public static function approve(Deal $deal, string $by): void
    {
        abort_unless(in_array($deal->copy_status, ['pending', 'changes'], true), 409, 'There’s no copy waiting for approval.');
        $deal->forceFill(['copy_status' => 'approved', 'copy_approved_at' => now(), 'copy_note' => null])->saveQuietly();
        ClientNotifier::event($deal, 'copy', "Sponsored copy approved by {$by}");
        \App\Http\Controllers\Api\PortalController::tellAdmins("Copy approved: {$deal->brand_name}", "{$by} approved the sponsored copy.\n\n" . url('/deals'));
    }

    public static function requestChanges(Deal $deal, string $by, string $note): void
    {
        abort_unless($deal->copy_status === 'pending', 409, 'There’s no copy waiting for approval.');
        $deal->forceFill(['copy_status' => 'changes', 'copy_note' => $note])->saveQuietly();
        ClientNotifier::event($deal, 'copy', "Changes asked on the sponsored copy by {$by}");
        \App\Http\Controllers\Api\PortalController::tellAdmins("Copy changes: {$deal->brand_name}", "{$by} asked for changes to the sponsored copy:\n\n{$note}\n\n" . url('/deals'));
    }

    /**
     * Why this post can't go out yet (null = it can).
     */
    public static function blockReason(SocialPost $post): ?string
    {
        $deal = Deal::where('social_post_id', $post->id)->whereIn('copy_status', ['pending', 'changes'])->where('copy_override', false)->first();

        return $deal ? "Waiting for {$deal->brand_name} to approve the sponsored copy" . ($deal->copy_status === 'changes' ? ' (they asked for changes)' : '') . '.' : null;
    }

    /**
     * Daily: the day before and on the due date. Returns reminders sent.
     */
    public static function remind(): int
    {
        $sent = 0;
        Deal::where('copy_status', 'pending')->whereNotNull('copy_due_on')->where('copy_reminders', '<', 2)
            ->whereDate('copy_due_on', '<=', now()->addDay()->toDateString())->get()->each(function (Deal $d) use (&$sent) {
                if ($d->copy_reminders > 0 && $d->updated_at?->isToday()) {
                    return;
                }
                ClientNotifier::email($d, "Reminder: approve the sponsored copy for {$d->brand_name}", 'The sponsored copy is waiting for your approval (due ' . $d->copy_due_on->toFormattedDateString() . ').');
                $d->forceFill(['copy_reminders' => $d->copy_reminders + 1])->save();
                $sent++;
            });

        return $sent;
    }
}
