<?php

namespace App\Http\Controllers\Api;

use App\Domains\Social\Connect\ConnectException;
use App\Domains\Social\Connect\Connections;
use App\Domains\Social\Models\SocialAccount;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Connect her social accounts (OAuth), keep them fresh, and pull their numbers. */
class SocialAccountsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'app_url' => rtrim((string) config('app.url'), '/'),
            'https' => str_starts_with((string) config('app.url'), 'https://'),
            'networks' => Connections::catalog(),
        ]);
    }

    /** Save a network's app keys (the secret is encrypted; an empty secret keeps the saved one). */
    public function saveApp(Request $request, string $platform): JsonResponse
    {
        $this->known($platform);
        $data = $request->validate([
            'client_id' => ['nullable', 'string', 'max:300'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'clear' => ['nullable', 'boolean'],
            'x_monthly_requests' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        if (! empty($data['clear'])) {
            Setting::put("{$platform}_client_id", null);
            Setting::put("{$platform}_client_secret", null);
        } else {
            Setting::put("{$platform}_client_id", $data['client_id'] ?? null);
            if (! empty($data['client_secret'])) {
                Setting::put("{$platform}_client_secret", $data['client_secret']);
            }
        }
        if ($platform === 'x' && array_key_exists('x_monthly_requests', $data)) {
            Setting::put('x_monthly_requests', (string) (int) $data['x_monthly_requests']);
        }

        return response()->json(['message' => 'Saved.', 'networks' => Connections::catalog()]);
    }

    public function connect(Request $request, string $platform): JsonResponse
    {
        $this->known($platform);
        try {
            return response()->json(['url' => Connections::start($platform, (int) Auth::id())]);
        } catch (ConnectException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Where the network sends her back (a browser redirect, so a web route). */
    public function callback(Request $request, string $platform): RedirectResponse
    {
        if (! isset(Connections::PROVIDERS[$platform])) {
            abort(404);
        }
        $back = fn (array $q) => redirect('/connections?'.http_build_query($q));
        if (! Auth::check()) {
            return $back(['error' => 'You were signed out of the studio. Sign in, then press Connect again.', 'network' => $platform]);
        }
        if ($request->filled('error')) {
            // She pressed Cancel, or the network refused (e.g. not added as a tester).
            $msg = (string) ($request->query('error_description') ?: $request->query('error_message') ?: $request->query('error'));

            return $back(['error' => mb_substr(str_replace('+', ' ', $msg), 0, 200), 'network' => $platform]);
        }
        try {
            $account = Connections::finish($platform, $request->query('state'), $request->query('code'), (int) Auth::id());
            try {
                Connections::sync($account); // first numbers straight away; a failure here isn't a failed connection
            } catch (Throwable) {
            }

            return $back(['connected' => $platform]);
        } catch (Throwable $e) {
            report($e);

            return $back(['error' => $e instanceof ConnectException ? $e->getMessage() : 'Something went wrong finishing the sign-in.', 'network' => $platform]);
        }
    }

    public function sync(string $platform): JsonResponse
    {
        $this->known($platform);
        $account = SocialAccount::where('platform', $platform)->firstOrFail();
        @set_time_limit(180);
        try {
            return response()->json(['message' => Connections::sync($account), 'networks' => Connections::catalog()]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'networks' => Connections::catalog()], 422);
        }
    }

    /** Sync every connected account, or only the ones not synced in the last hour (?stale=1, used on app open). */
    public function syncAll(Request $request): JsonResponse
    {
        @set_time_limit(300);

        return response()->json(['results' => Connections::syncAll($request->boolean('stale'))]);
    }

    public function disconnect(string $platform): JsonResponse
    {
        $this->known($platform);
        Connections::disconnect($platform);

        return response()->json(['message' => 'Disconnected.', 'networks' => Connections::catalog()]);
    }

    private function known(string $platform): void
    {
        if (! isset(Connections::PROVIDERS[$platform])) {
            abort(404, 'Unknown network.');
        }
    }
}
