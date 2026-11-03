<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Clients\DealFiles;
use App\Domains\Community\Community;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Digital products: templates, presets, guides, recorded workshops sold once by card
 * (Stripe Checkout, kind=product). The buyer gets an emailed download link that works 5
 * times within 30 days; signed-in buyers also find it under their purchases. Pro members
 * get her Pro discount when she sets one.
 */
class ShopController extends Controller
{
    public const LINK_DAYS = 30;

    public const FILE_TYPES = ['zip', 'pdf', 'mp4', 'mov', 'png', 'jpg', 'psd', 'ai', 'fig', 'sketch', 'lut', 'cube', 'xmp', 'dng', 'notion', 'csv', 'xlsx', 'docx', 'pptx', 'key', 'md', 'txt', 'json'];

    // ==========================================================================================
    // Public
    // ==========================================================================================

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $pro = $user ? Community::isPro($user) : false;

        return response()->json([
            'products' => DB::table('products')->where('status', 'published')->whereNotNull('file_path')->orderByDesc('id')->get()
                ->map(fn ($p) => $this->publicRow($p, $pro)),
            'online' => StripePaymentController::configured(),
            'signed_in' => (bool)$user,
            'purchases' => $user ? $this->purchasesFor($user->id) : [],
        ]);
    }

    public function checkout(Request $request, string $slug): JsonResponse
    {
        $p = DB::table('products')->where('slug', $slug)->where('status', 'published')->whereNotNull('file_path')->first();
        abort_unless($p, 404);
        $user = $request->user();
        $data = $request->validate(['email' => [$user ? 'nullable' : 'required', 'email', 'max:200']]);
        $email = $user?->email ?? strtolower(trim($data['email']));
        $price = $this->priceFor($p, $user ? Community::isPro($user) : false);
        $secret = StripePaymentController::secret();
        abort_unless($secret && $price > 0, 409, 'Online payment isn’t available right now.');

        $orderId = DB::table('orders')->insertGetId([
            'product_id' => $p->id, 'user_id' => $user?->id, 'email' => $email,
            'amount' => $price, 'currency' => $p->currency, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $meta = ['kind' => 'product', 'order_id' => $orderId];
        try {
            $r = Http::withToken($secret)->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => ['currency' => strtolower($p->currency), 'product_data' => ['name' => $p->title], 'unit_amount' => (int)round($price * 100)],
                    'quantity' => 1,
                ]],
                'customer_email' => $email,
                'success_url' => url('/shop?bought=' . $p->slug),
                'cancel_url' => url('/shop?cancelled=1'),
                'metadata' => $meta,
                'payment_intent_data' => ['metadata' => $meta],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe product checkout failed', ['error' => $e->getMessage()]);
            $r = null;
        }
        if (!$r?->successful() || !$r->json('url')) {
            DB::table('orders')->where('id', $orderId)->delete();
            abort(502, 'Payment couldn’t start. Please try again in a minute.');
        }
        DB::table('orders')->where('id', $orderId)->update(['stripe_session_id' => (string)$r->json('id')]);

        return response()->json(['checkout_url' => $r->json('url')]);
    }

    public static function isProduct(array $session): bool
    {
        return ($session['metadata']['kind'] ?? null) === 'product';
    }

    /**
     * Webhook: the order is paid → a download link by email (once per order).
     */
    public static function completed(array $session): void
    {
        $order = DB::table('orders')->where('id', (int)($session['metadata']['order_id'] ?? 0))->first();
        if (!$order || $order->status === 'paid' || ($session['payment_status'] ?? 'paid') !== 'paid') {
            return;
        }
        $token = Str::random(48);
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => isset($session['amount_total']) ? $session['amount_total'] / 100 : $order->amount,
            'download_token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::LINK_DAYS),
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $order->product_id)->increment('sales_count');
        $p = DB::table('products')->where('id', $order->product_id)->first();
        AuthController::mail($order->email, "Your download: {$p->title}", "Thank you for buying “{$p->title}”!\n\nDownload it here (works 5 times within " . self::LINK_DAYS . " days):\n"
            . url('/api/shop/download/' . $token) . "\n\nWith an account on the site, you’ll also find it under Shop → Your purchases.");
        AgentEvent::log('payment', "Sold: {$p->title} to {$order->email}, " . number_format((float)$order->amount, 2) . " {$order->currency}.");
    }

    /**
     * Public: the emailed link.
     */
    public function download(string $token): Response
    {
        $order = DB::table('orders')->where('download_token_hash', hash('sha256', $token))->where('status', 'paid')->first();
        abort_unless($order, 404, 'This download link isn’t valid.');
        abort_if($order->expires_at && now()->greaterThan($order->expires_at), 410, 'This download link has expired. Reply to your receipt email for a new one.');
        abort_if($order->downloads >= $order->max_downloads, 410, 'This link has been used the maximum number of times. Reply to your receipt email for a new one.');

        return $this->send($order);
    }

    /**
     * Signed-in buyers download from their purchases.
     */
    public function downloadMine(Request $request, int $order): Response
    {
        $o = DB::table('orders')->where('id', $order)->where('status', 'paid')
            ->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereRaw('lower(email) = ?', [strtolower($request->user()->email)]))->first();
        abort_unless($o, 404);
        abort_if($request->user()->needsEmailVerification(), 403, 'Confirm your email first.');

        return $this->send($o);
    }

    // ==========================================================================================
    // Her side
    // ==========================================================================================

    public function admin(): JsonResponse
    {
        $since = now()->subDays(30);

        return response()->json([
            'products' => DB::table('products')->orderByDesc('id')->get()->map(fn ($p) => (array)$p + [
                'revenue' => (float)DB::table('orders')->where('product_id', $p->id)->where('status', 'paid')->sum('amount'),
                'has_file' => (bool)$p->file_path,
            ]),
            'orders' => DB::table('orders')->join('products', 'products.id', '=', 'orders.product_id')->where('orders.status', 'paid')
                ->orderByDesc('orders.paid_at')->take(100)->get(['orders.id', 'orders.email', 'orders.amount', 'orders.currency', 'orders.paid_at', 'orders.downloads', 'products.title']),
            'totals' => [
                'revenue_30d' => (float)DB::table('orders')->where('status', 'paid')->where('paid_at', '>=', $since)->sum('amount'),
                'orders_30d' => DB::table('orders')->where('status', 'paid')->where('paid_at', '>=', $since)->count(),
                'revenue_all' => (float)DB::table('orders')->where('status', 'paid')->sum('amount'),
            ],
            'online' => StripePaymentController::configured(),
        ]);
    }

    public function save(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'title' => [$id ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => 'nullable|string|max:10000',
            'price' => [$id ? 'sometimes' : 'required', 'numeric', 'min:0.5', 'max:100000'],
            'currency' => 'nullable|string|size:3',
            'pro_discount_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'sometimes|in:draft,published',
        ]);
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        if ($id) {
            $p = DB::table('products')->where('id', $id)->first();
            abort_unless($p, 404);
            abort_if(($data['status'] ?? null) === 'published' && !$p->file_path, 422, 'Upload the file first.');
            DB::table('products')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $slug = Str::slug($data['title']) ?: 'product';
            $base = $slug;
            $n = 2;
            while (DB::table('products')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $n++;
            }
            DB::table('products')->insert($data + ['slug' => $slug, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->admin();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    public function upload(Request $request, int $id): JsonResponse
    {
        $p = DB::table('products')->where('id', $id)->first();
        abort_unless($p, 404);
        $request->validate(['file' => 'required|file|extensions:' . implode(',', self::FILE_TYPES) . '|max:' . (max(DealFiles::maxMb(), 200) * 1024)]);
        $file = $request->file('file');
        $path = $file->storeAs('products', bin2hex(random_bytes(10)) . '.' . strtolower($file->getClientOriginalExtension()), 'local');
        if ($p->file_path) {
            Storage::disk('local')->delete($p->file_path);
        }
        DB::table('products')->where('id', $id)->update([
            'file_path' => $path,
            'file_name' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 0, 150),
            'updated_at' => now(),
        ]);

        return $this->admin();
    }

    public function destroy(int $id): JsonResponse
    {
        $sold = DB::table('orders')->where('product_id', $id)->where('status', 'paid')->exists();
        if ($sold) {
            // Buyers keep their downloads: take it off sale instead.
            DB::table('products')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        } else {
            $p = DB::table('products')->where('id', $id)->first();
            if ($p?->file_path) {
                Storage::disk('local')->delete($p->file_path);
            }
            DB::table('products')->where('id', $id)->delete();
        }

        return $this->admin();
    }

    /**
     * A fresh download link for a buyer (5 more downloads, 30 more days), emailed to them.
     */
    public function resend(int $order): JsonResponse
    {
        $o = DB::table('orders')->where('id', $order)->where('status', 'paid')->first();
        abort_unless($o, 404);
        $token = Str::random(48);
        DB::table('orders')->where('id', $o->id)->update(['download_token_hash' => hash('sha256', $token), 'downloads' => 0, 'expires_at' => now()->addDays(self::LINK_DAYS), 'updated_at' => now()]);
        $p = DB::table('products')->where('id', $o->product_id)->first();
        AuthController::mail($o->email, "Your download: {$p->title}", "Here’s a fresh download link for “{$p->title}”:\n" . url('/api/shop/download/' . $token));

        return response()->json(['ok' => true]);
    }

    // ==========================================================================================

    private function send(object $order): Response
    {
        $p = DB::table('products')->where('id', $order->product_id)->first();
        abort_unless($p && $p->file_path && Storage::disk('local')->exists($p->file_path), 404, 'The file isn’t available right now.');
        DB::table('orders')->where('id', $order->id)->increment('downloads');

        return Storage::disk('local')->download($p->file_path, $p->file_name ?: basename($p->file_path), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function priceFor(object $p, bool $pro): float
    {
        $price = (float)$p->price;

        return $pro && $p->pro_discount_percent > 0 ? round($price * (100 - $p->pro_discount_percent) / 100, 2) : $price;
    }

    private function publicRow(object $p, bool $pro): array
    {
        return [
            'slug' => $p->slug,
            'title' => $p->title,
            'description' => $p->description,
            'price' => (float)$p->price,
            'your_price' => $this->priceFor($p, $pro),
            'currency' => $p->currency,
            'pro_discount_percent' => (int)$p->pro_discount_percent,
            'file_type' => strtoupper(pathinfo((string)$p->file_name, PATHINFO_EXTENSION)),
        ];
    }

    private function purchasesFor(int $userId): array
    {
        $email = (string)DB::table('users')->where('id', $userId)->value('email');

        return DB::table('orders')->join('products', 'products.id', '=', 'orders.product_id')->where('orders.status', 'paid')
            ->where(fn ($q) => $q->where('orders.user_id', $userId)->orWhereRaw('lower(orders.email) = ?', [strtolower($email)]))
            ->orderByDesc('orders.paid_at')->get(['orders.id', 'orders.paid_at', 'products.title', 'products.file_name'])->map(fn ($o) => (array)$o)->all();
    }
}
