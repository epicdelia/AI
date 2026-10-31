<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Landing\Pricing;
use App\Http\Controllers\Controller;
use App\Models\Deal;
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
        ]);

        $pkg = Pricing::package($data['package_id']);
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
                ['platform' => 'stripe', 'format' => $pkg['name'], 'package' => $pkg['id']],
            ],
            'key_talking_points' => $pkg['description'],
            'notes' => 'Checkout started on the website for ' . $pkg['name'] . '. Notes: ' . (($data['notes'] ?? null) ?: 'None'),
        ]);

        if ($secret !== null) {
            try {
                $resp = Http::withToken($secret)
                    ->asForm()
                    ->post('https://api.stripe.com/v1/checkout/sessions', [
                        'payment_method_types' => ['card'],
                        'line_items' => [[
                            'price_data' => [
                                'currency' => $pkg['currency'],
                                'product_data' => [
                                    'name' => 'Tech Unicorn: ' . $pkg['name'],
                                    'description' => $pkg['description'],
                                ],
                                'unit_amount' => $pkg['amount'],
                            ] + ($pkg['interval'] ? ['recurring' => ['interval' => $pkg['interval']]] : []),
                            'quantity' => 1,
                        ]],
                        // The retainer bills monthly until cancelled; everything else is one payment.
                        'mode' => $pkg['interval'] ? 'subscription' : 'payment',
                        'customer_email' => $customerEmail,
                        'success_url' => url('/?payment=success&deal=' . $deal->id),
                        'cancel_url' => url('/?payment=cancelled&deal=' . $deal->id),
                        'metadata' => [
                            'deal_id' => $deal->id,
                            'package_id' => $pkg['id'],
                        ],
                    ]);

                if ($resp->successful() && $resp->json('url')) {
                    AgentEvent::log('lead', "Checkout started: {$companyName} ({$customerEmail}), {$pkg['name']}, {$price}.");

                    return response()->json([
                        'status' => 'redirect',
                        'paid' => false,
                        'checkout_url' => $resp->json('url'),
                        'session_id' => $resp->json('id'),
                        'deal_id' => $deal->id,
                    ]);
                }
                Log::warning('Stripe checkout session failed', ['status' => $resp->status(), 'error' => $resp->json('error.message')]);
            } catch (\Throwable $e) {
                Log::warning('Stripe checkout session failed', ['error' => $e->getMessage()]);
            }
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
        if (! $secret || ! self::validSignature($request->getContent(), (string) $request->header('Stripe-Signature'), $secret)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        if (($payload['type'] ?? '') === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $deal = Deal::find($session['metadata']['deal_id'] ?? null);
            if ($deal && ($session['payment_status'] ?? 'paid') === 'paid' && $deal->payment_status !== 'paid') {
                $deal->update([
                    'stage' => 'paid',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
                AgentEvent::log('payment', "Paid via Stripe: {$deal->brand_name}, $" . number_format((float) $deal->rate) . '.');
            }
        }

        return response()->json(['received' => true]);
    }

    private static function secret(): ?string
    {
        return Setting::get('stripe_secret_key') ?: (env('STRIPE_SECRET_KEY') ?: null);
    }

    /** Stripe-Signature is "t=<unix>,v1=<hex HMAC-SHA256 of "<t>.<payload>">"; reject anything older than 5 minutes. */
    public static function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = (int) ($parts['t'][0] ?? 0);
        if (! $t || abs(time() - $t) > $tolerance) {
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
}
