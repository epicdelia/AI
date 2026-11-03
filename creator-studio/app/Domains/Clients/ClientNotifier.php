<?php

namespace App\Domains\Clients;

use App\Domains\Clients\Models\DealEvent;
use App\Http\Controllers\Api\AuthController;
use App\Models\Deal;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Prompts;

/**
 * Keeps clients in the loop when she moves a request along: a timeline entry (worded for them)
 * and an email for what matters: a proposal to read, a new status, an invoice, a delivery. When
 * a request is paid, a review request appears on their dashboard. Only for deals linked to a
 * client; never blocks her save.
 */
class ClientNotifier
{
    public static function created(Deal $deal): void
    {
        if ($deal->user_id && $deal->stage !== 'prospect') {
            self::event($deal, 'requested', 'Request received');
        }
    }

    public static function updated(Deal $deal): void
    {
        if (!$deal->user_id || $deal->stage === 'prospect') {
            return;
        }
        // Linked just now (by her, or on email confirmation): start their timeline, no emails.
        if ($deal->wasChanged('user_id')) {
            if (!DealEvent::where('deal_id', $deal->id)->exists()) {
                self::event($deal, 'requested', 'Request received');
            }

            return;
        }
        $title = ClientDeal::summary($deal)['title'];
        $before = (new Deal())->setRawAttributes($deal->getRawOriginal());
        $was = ClientDeal::status($before);
        $now = ClientDeal::status($deal);

        if ($deal->proposal && !$before->proposal_at && $deal->proposal_at) {
            self::event($deal, 'proposal', 'Proposal sent');
            self::email($deal, "Your proposal is ready: {$title}", "Your proposal for \"{$title}\" is ready to read. You can accept it (and pay, if it applies) in your dashboard.");
        } elseif ($was['key'] !== $now['key']) {
            self::event($deal, $now['key'], $now['label']);
            $line = match ($now['key']) {
                'invoiced' => "Your invoice for \"{$title}\" is ready." . (ClientDeal::canPay($deal) ? ' You can pay it online in your dashboard.' : ''),
                'in_review' => "\"{$title}\" is ready for your review. Approve it or ask for changes in your dashboard.",
                'paid' => "Payment received for \"{$title}\". Thank you!",
                'closed' => "\"{$title}\" has been closed.",
                default => "\"{$title}\" is now: {$now['label']}.",
            };
            self::email($deal, "Update: {$title}", $line);
        }

        foreach (self::newDeliveries($before->deliverables ?? [], $deal->deliverables ?? []) as $name) {
            self::event($deal, 'delivered', "Delivered: {$name}");
            self::email($deal, "New delivery: {$name}", "\"{$name}\" for \"{$title}\" has been delivered. Open it in your dashboard.");
        }

        if ($deal->stage === 'paid' && $before->stage !== 'paid') {
            Testimonial::requestFor($deal); // a "Leave a review" card on their dashboard
        }
    }

    public static function event(Deal $deal, string $kind, string $label): void
    {
        DealEvent::create([
            'deal_id' => $deal->id,
            'kind' => $kind,
            'label' => mb_substr($label, 0, 250),
        ]);
    }

    /**
     * Email the client (and teammates) about a request, unless they turned emails off.
     */
    public static function email(Deal $deal, string $subject, string $line): void
    {
        rescue(fn () => ClientWebhookDispatcher::dispatch($deal, 'update', $subject, $line), null, false);

        foreach (self::recipients($deal) as $user) {
            AuthController::mail(
                $user->email,
                $subject,
                "Hi {$user->name},\n\n{$line}\n\n" . url('/requests/' . $deal->id) . "\n\n— " . Prompts::name() . "\n\n(Turn these emails off in Account & security.)"
            );
        }
    }

    /**
     * Deliverables that just got a link or the status "delivered".
     */
    private static function newDeliveries(array $old, array $new): array
    {
        $done = fn ($d) => is_array($d) && (!empty($d['url']) || strtolower((string)($d['status'] ?? '')) === 'delivered');
        $key = fn ($d) => strtolower((string)($d['format'] ?? $d['service'] ?? ''));
        $before = collect($old)->filter($done)->map($key)->all();

        return collect($new)->filter(fn ($d) => $done($d) && ($d['platform'] ?? null) !== 'meeting' && !in_array($key($d), $before, true))
            ->map(fn ($d) => (string)($d['format'] ?? $d['service'] ?? 'Deliverable'))->values()->all();
    }

    /**
     * The client and their teammates who want emails.
     */
    private static function recipients(Deal $deal): array
    {
        $owner = User::find($deal->user_id);
        if (!$owner) {
            return [];
        }

        return User::where(fn ($q) => $q->whereKey($owner->id)->orWhere('team_owner_id', $owner->id))
            ->whereNull('disabled_at')->whereNotNull('email_verified_at')->where('notify_email', true)
            ->where('email', '!=', DemoClient::EMAIL)->get()->all();
    }
}
