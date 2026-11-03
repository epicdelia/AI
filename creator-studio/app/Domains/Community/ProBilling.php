<?php

namespace App\Domains\Community;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Community\Models\CommunityMembership;
use App\Http\Controllers\Api\StripePaymentController;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The paid community tier on Stripe: a subscription Checkout tagged kind=community, and the
 * webhook events that start, renew and end a membership.
 */
class ProBilling
{
    /**
     * @return array{url: string, id: string}|null
     */
    public static function checkout(User $user): ?array
    {
        $secret = StripePaymentController::secret();
        $c = Community::proConfig();
        if (!$secret || !Community::proAvailable()) {
            return null;
        }
        $meta = [
            'kind' => 'community',
            'user_id' => $user->id,
        ];
        try {
            $resp = Http::withToken($secret)->asForm()->post('https://api.stripe.com/v1/checkout/sessions', array_filter([
                'mode' => 'subscription',
                'line_items' => [[
                    'price_data' => [
                        'currency' => $c['currency'],
                        'product_data' => ['name' => 'Community ' . $c['name']],
                        'unit_amount' => (int)round($c['amount'] * 100),
                        'recurring' => ['interval' => $c['interval']],
                    ],
                    'quantity' => 1,
                ]],
                'customer' => $user->stripe_customer_id ?: null,
                'customer_email' => $user->stripe_customer_id ? null : $user->email,
                'success_url' => url('/community?pro=success'),
                'cancel_url' => url('/community?pro=cancelled'),
                'metadata' => $meta,
                'subscription_data' => ['metadata' => $meta],
            ]));
            if ($resp->successful() && $resp->json('url')) {
                return ['url' => (string)$resp->json('url'), 'id' => (string)$resp->json('id')];
            }
            Log::warning('Stripe community checkout failed', ['status' => $resp->status(), 'error' => $resp->json('error.message')]);
        } catch (\Throwable $e) {
            Log::warning('Stripe community checkout failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    public static function isCommunity(array $object): bool
    {
        return ($object['metadata']['kind'] ?? null) === 'community';
    }

    /**
     * checkout.session.completed for kind=community.
     */
    public static function completed(array $session): void
    {
        $user = User::find($session['metadata']['user_id'] ?? null);
        if (!$user || ($session['payment_status'] ?? 'paid') !== 'paid') {
            return;
        }
        if (!empty($session['customer']) && !$user->stripe_customer_id) {
            $user->forceFill(['stripe_customer_id' => (string)$session['customer']])->save();
        }
        CommunityMembership::updateOrCreate(['user_id' => $user->id], [
            'tier' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => $session['subscription'] ?? null,
            'current_period_end' => now()->addMonth()->addDays(3), // the invoice webhook sets the real end
        ]);
        AgentEvent::log('payment', "New community Pro member: {$user->name}.");
    }

    /**
     * invoice.paid: true when it belonged to a community membership (then it's handled here).
     */
    public static function invoicePaid(array $invoice): bool
    {
        $sub = $invoice['subscription'] ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        $m = $sub ? CommunityMembership::where('stripe_subscription_id', $sub)->first() : null;
        if (!$m) {
            return false;
        }
        $end = $invoice['lines']['data'][0]['period']['end'] ?? null;
        $m->update([
            'status' => 'active',
            'current_period_end' => $end ? CarbonImmutable::createFromTimestamp((int)$end)->addDays(1) : now()->addMonth()->addDays(3),
        ]);

        return true;
    }

    /**
     * customer.subscription.deleted: true when it was a community membership.
     */
    public static function ended(array $sub): bool
    {
        $m = !empty($sub['id']) ? CommunityMembership::where('stripe_subscription_id', $sub['id'])->first() : null;
        if (!$m) {
            return false;
        }
        $m->update(['status' => 'cancelled']);
        AgentEvent::log('payment', 'Community Pro membership ended: ' . ($m->user?->name ?? 'a member') . '.');

        return true;
    }
}
