<?php

namespace App\Domains\Billing;

use App\Http\Controllers\Api\StripePaymentController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe's hosted Customer Portal: a client or Pro member updates their card, downloads
 * invoices or cancels a subscription without writing to her. Needs the "Customer portal"
 * turned on once in the Stripe dashboard (Settings → Billing → Customer portal).
 */
class BillingPortal
{
    public static function url(?string $customerId, string $returnUrl): ?string
    {
        $secret = StripePaymentController::secret();
        if (!$secret || !$customerId) {
            return null;
        }
        try {
            $resp = Http::withToken($secret)->asForm()->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);
            if ($resp->successful() && $resp->json('url')) {
                return (string)$resp->json('url');
            }
            Log::warning('Stripe billing portal failed', ['status' => $resp->status(), 'error' => $resp->json('error.message')]);
        } catch (\Throwable $e) {
            Log::warning('Stripe billing portal failed', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
