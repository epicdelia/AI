<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Landing\Attribution;
use App\Domains\Landing\Pricing;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripePaymentController extends Controller
{
    public const PACKAGES = [
        'retainer' => [
            'id' => 'retainer',
            'name' => 'Advisory Retainer (Monthly)',
            'description' => 'Ex-Google engineer on call: 2 strategy calls, unlimited async reviews, 24h response.',
            'amount' => 350000, // in cents ($3,500.00)
            'currency' => 'usd',
            'interval' => 'month',
        ],
        'mvp_deposit' => [
            'id' => 'mvp_deposit',
            'name' => 'MVP Build: 50% Project Deposit',
            'description' => '50% upfront project deposit for 2-4 week fixed MVP build with Friday live demos.',
            'amount' => 600000, // in cents ($6,000.00)
            'currency' => 'usd',
            'interval' => null,
        ],
        'mvp_full' => [
            'id' => 'mvp_full',
            'name' => 'MVP Build: Full Fixed Package',
            'description' => 'Complete fixed-scope MVP: UX design, AI build, cloud deployment, and 30-day post-launch support.',
            'amount' => 1200000, // in cents ($12,000.00)
            'currency' => 'usd',
            'interval' => null,
        ],
        'brand_partnership' => [
            'id' => 'brand_partnership',
            'name' => 'Brand Partnership Campaign',
            'description' => 'A dedicated technical integration across her biggest networks, with full digital ad rights.',
            'amount' => 500000, // in cents ($5,000.00)
            'currency' => 'usd',
            'interval' => null,
        ],
        'media_kit_sponsor' => [
            'id' => 'media_kit_sponsor',
            'name' => 'Verified Media Kit Feature',
            'description' => 'Sponsored placement and product spotlight in Delia’s verified press & media kit.',
            'amount' => 150000, // in cents ($1,500.00)
            'currency' => 'usd',
            'interval' => null,
        ],
        'advisory_session' => [
            'id' => 'advisory_session',
            'name' => '90-Minute Architecture & Strategy Session',
            'description' => '90-minute 1-on-1 architecture review, code audit and AI product roadmap.',
            'amount' => 50000, // in cents ($500.00)
            'currency' => 'usd',
            'interval' => null,
        ],
    ];

    /**
     * Get available Stripe packages.
     */
    public function packages(): JsonResponse
    {
        return response()->json([
            // Prices as her dashboard sets them (Packages & pricing), the same the cards show.
            'packages' => Pricing::packages(),
            'package_list' => array_values(Pricing::packages()),
            'stripe_configured' => self::secret() !== null,
            'publishable_key' => Setting::get('stripe_publishable_key') ?: env('STRIPE_KEY'),
        ]);
    }

    /**
     * Start a Stripe Checkout Session. The deal is logged as a pending inquiry and only becomes
     * paid when Stripe's signed webhook says so. Without Stripe set up nothing is charged and the
     * visitor is told an invoice will follow.
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => 'required|string|in:' . implode(',', array_keys(self::PACKAGES)),
            'customer_name' => 'required|string|max:150',
            'customer_email' => 'required|email|max:150',
            'company' => 'nullable|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ] + Attribution::rules());

        $pkg = Pricing::package($data['package_id']);
        $from = Attribution::resolve($data['attribution'] ?? null, $request->getHost());
        $secret = self::secret();
        $customerEmail = $data['customer_email'];
        $customerName = $data['customer_name'];
        $companyName = ($data['company_name'] ?? null) ?: ($data['company'] ?? null) ?: $customerName;
        $amount = $pkg['amount'] / 100;
        $price = '$' . number_format($amount);

        $deal = Deal::create([
            'brand_name' => $companyName,
            'contact_name' => $customerName,
            'contact_email' => $customerEmail,
            'stage' => 'inquiry',
            'rate' => $amount,
            'currency' => 'USD',
            'payment_status' => 'pending',
            'deliverables' => [
                [
                    'platform' => 'stripe',
                    'format' => $pkg['name'],
                    'package' => $pkg['id'],
                ],
            ],
            'key_talking_points' => $pkg['description'],
            'notes' => 'Checkout started on the website for ' . $pkg['name'] . '. Notes: ' . (($data['notes'] ?? null) ?: 'None'),
            'source' => $from['source'],
            'attribution' => $from['attribution'],
        ]);

        $session = $secret !== null ? self::startCheckout($deal, $pkg, $customerEmail) : null;
        if ($session) {
            AgentEvent::log('lead', "Checkout started: {$companyName} ({$customerEmail}), {$pkg['name']}, {$price}.");

            return response()->json([
                'status' => 'redirect',
                'paid' => false,
                'checkout_url' => $session['url'],
                'session_id' => $session['id'],
                'deal_id' => $deal->id,
            ]);
        }

        AgentEvent::log('lead', "Invoice requested: {$companyName} ({$customerEmail}), {$pkg['name']}, {$price}.");

        return response()->json([
            'status' => 'requested',
            'paid' => false,
            'package_id' => $data['package_id'],
            'package' => $pkg,
            'amount' => $amount,
            'currency' => 'USD',
            'deal_id' => $deal->id,
            'message' => 'Thanks! Online card payment isn’t available right now, so nothing was charged. You’ll get an invoice for ' . $pkg['name'] . ' by email.',
        ]);
    }

    /**
     * Stripe webhook: only a correctly signed checkout.session.completed marks a deal paid.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = Setting::get('stripe_webhook_secret') ?: env('STRIPE_WEBHOOK_SECRET');
        if (!$secret || !self::validSignature($request->getContent(), (string)$request->header('Stripe-Signature'), $secret)) {
            return response()->json([
                'error' => 'Invalid signature',
            ], 400);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $object = $payload['data']['object'] ?? [];
        match ($payload['type'] ?? '') {
            'checkout.session.completed' => \App\Domains\Community\ProBilling::isCommunity($object)
                ? \App\Domains\Community\ProBilling::completed($object)
                : (ShopController::isProduct($object) ? ShopController::completed($object) : self::checkoutCompleted($object)),
            'invoice.paid', 'invoice.payment_succeeded' => \App\Domains\Community\ProBilling::invoicePaid($object) ?: self::invoicePaid($object),
            'customer.subscription.deleted' => \App\Domains\Community\ProBilling::ended($object) ?: self::subscriptionEnded($object),
            default => null,
        };

        return response()->json([
            'received' => true,
        ]);
    }

    /**
     * A Stripe Checkout Session for a deal: {url, id}, or null when Stripe isn't set up or says no.
     * $pkg is {name, description, amount (cents), currency, interval}. The webhook marks it paid.
     */
    public static function startCheckout(Deal $deal, array $pkg, ?string $email = null, ?string $returnTo = null, array $meta = []): ?array
    {
        $secret = self::secret();
        if ($secret === null) {
            return null;
        }

        try {
            $resp = Http::withToken($secret)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', array_filter([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => $pkg['currency'] ?? 'usd',
                            'product_data' => array_filter([
                                'name' => 'Tech Unicorn: ' . $pkg['name'],
                                'description' => $pkg['description'] ?? null,
                            ]),
                            'unit_amount' => (int)$pkg['amount'],
                        ] + (!empty($pkg['interval']) ? [
                            'recurring' => [
                                'interval' => $pkg['interval'],
                            ],
                        ] : []),
                        'quantity' => 1,
                    ]],
                    // The retainer bills monthly until cancelled; everything else is one payment.
                    'mode' => !empty($pkg['interval']) ? 'subscription' : 'payment',
                    'customer_email' => $email,
                    'success_url' => url(($returnTo ?? '/') . '?payment=success&deal=' . $deal->id),
                    'cancel_url' => url(($returnTo ?? '/') . '?payment=cancelled&deal=' . $deal->id),
                    'metadata' => [
                        'deal_id' => $deal->id,
                        'package_id' => $pkg['id'] ?? 'proposal',
                    ] + $meta,
                    'subscription_data' => !empty($pkg['interval']) ? [
                        'metadata' => [
                            'deal_id' => $deal->id,
                        ],
                    ] : null,
                ]));

            if ($resp->successful() && $resp->json('url')) {
                return [
                    'url' => $resp->json('url'),
                    'id' => $resp->json('id'),
                ];
            }
            Log::warning('Stripe checkout session failed', [
                'status' => $resp->status(),
                'error' => $resp->json('error.message'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe checkout session failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public static function configured(): bool
    {
        return self::secret() !== null;
    }

    /**
     * Stripe-Signature is "t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<payload>">"; reject anything older than 5 minutes.
     */
    public static function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = (int)($parts['t'][0] ?? 0);
        if (!$t || abs(time() - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first payment: the deal is paid, and a retainer remembers its subscription for renewals.
     */
    private static function checkoutCompleted(array $session): void
    {
        $deal = Deal::find($session['metadata']['deal_id'] ?? null);
        if (!$deal || ($session['payment_status'] ?? 'paid') !== 'paid') {
            return;
        }
        $part = (string)($session['metadata']['part'] ?? 'full');
        if ($part === 'deposit' || str_starts_with($part, 'rights:')) {
            self::partPaid($deal, $session, $part);

            return;
        }
        // Remember the Stripe customer so the client can manage cards and invoices themselves.
        if (!empty($session['customer']) && !$deal->stripe_customer_id) {
            $deal->forceFill(['stripe_customer_id' => (string)$session['customer']])->save();
            if ($deal->user_id && ($client = \App\Models\User::find($deal->user_id)) && !$client->stripe_customer_id) {
                $client->forceFill(['stripe_customer_id' => (string)$session['customer']])->save();
            }
        }
        if (!empty($session['subscription']) && !$deal->stripe_subscription_id) {
            $deal->update([
                'stripe_subscription_id' => (string)$session['subscription'],
                'subscription_status' => 'active',
            ]);
        }
        if (!empty($session['id']) && isset($session['amount_total'])) {
            Payment::firstOrCreate([
                'stripe_id' => (string)$session['id'],
            ], [
                'deal_id' => $deal->id,
                'amount' => $session['amount_total'] / 100,
                'currency' => strtoupper((string)($session['currency'] ?? 'usd')),
                'kind' => 'checkout',
                'paid_at' => now(),
            ]);
        }
        if ($deal->payment_status !== 'paid') {
            $deal->update([
                'stage' => 'paid',
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);
            AgentEvent::log('payment', "Paid via Stripe: {$deal->brand_name}, $" . number_format((float)$deal->rate) . '.');
        }
    }

    /**
     * A retainer's monthly invoice: counted as a renewal (the first invoice is the checkout itself).
     * The subscription id sits on the invoice (older API) or under parent.subscription_details (newer).
     */
    private static function invoicePaid(array $invoice): void
    {
        if (($invoice['billing_reason'] ?? '') !== 'subscription_cycle' || empty($invoice['id'])) {
            return;
        }
        $sub = $invoice['subscription'] ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        $dealId = $invoice['subscription_details']['metadata']['deal_id'] ?? ($invoice['parent']['subscription_details']['metadata']['deal_id'] ?? null);
        $deal = ($sub ? Deal::where('stripe_subscription_id', $sub)->first() : null) ?? ($dealId ? Deal::find($dealId) : null);
        if (!$deal) {
            return;
        }
        $payment = Payment::firstOrCreate([
            'stripe_id' => (string)$invoice['id'],
        ], [
            'deal_id' => $deal->id,
            'amount' => ((int)($invoice['amount_paid'] ?? 0)) / 100,
            'currency' => strtoupper((string)($invoice['currency'] ?? 'usd')),
            'kind' => 'renewal',
            'paid_at' => isset($invoice['status_transitions']['paid_at']) ? \Carbon\CarbonImmutable::createFromTimestamp((int)$invoice['status_transitions']['paid_at']) : now(),
        ]);
        if ($payment->wasRecentlyCreated) {
            $deal->update([
                'subscription_status' => 'active',
            ]);
            AgentEvent::log('payment', "Retainer renewed: {$deal->brand_name}, $" . number_format($payment->amount) . '.');
            \App\Domains\Workflows\Engine::fire('payment', \App\Domains\Workflows\Engine::dealContext($deal, [
                'amount' => $payment->amount,
                'renewal' => true,
            ]), "pay:{$payment->id}");
        }
    }

    private static function subscriptionEnded(array $sub): void
    {
        $deal = !empty($sub['id']) ? Deal::where('stripe_subscription_id', $sub['id'])->first() : null;
        if ($deal && $deal->subscription_status !== 'cancelled') {
            $deal->update([
                'subscription_status' => 'cancelled',
            ]);
            AgentEvent::log('payment', "Retainer cancelled: {$deal->brand_name}.");
        }
    }

    /**
     * A deposit (the deal stays open for its balance) or a usage-rights extension.
     */
    private static function partPaid(Deal $deal, array $session, string $part): void
    {
        $amount = isset($session['amount_total']) ? $session['amount_total'] / 100 : 0;
        if (!empty($session['id'])) {
            $p = Payment::firstOrCreate(['stripe_id' => (string)$session['id']], [
                'deal_id' => $deal->id,
                'amount' => $amount,
                'currency' => strtoupper((string)($session['currency'] ?? 'usd')),
                'kind' => $part === 'deposit' ? 'deposit' : 'rights',
                'paid_at' => now(),
            ]);
            if (!$p->wasRecentlyCreated) {
                return; // Stripe retried the event
            }
        }
        if ($part === 'deposit') {
            $deal->forceFill(['deposit_paid_at' => now(), 'deposit_amount' => $amount, 'payment_status' => 'pending'])->save();
            AgentEvent::log('payment', "Deposit paid: {$deal->brand_name}, $" . number_format($amount, 2) . '.');
            \App\Domains\Clients\ClientNotifier::event($deal, 'deposit', 'Deposit received');

            return;
        }
        $right = \App\Domains\Deals\DealRight::where('deal_id', $deal->id)->find((int)substr($part, 7));
        if ($right) {
            \App\Domains\Deals\Rights::extend($right);
            AgentEvent::log('payment', "Rights extended: {$deal->brand_name} ({$right->kind}, +{$right->days} days).");
        }
    }

    public static function secret(): ?string
    {
        return Setting::get('stripe_secret_key') ?: (env('STRIPE_SECRET_KEY') ?: null);
    }
}
