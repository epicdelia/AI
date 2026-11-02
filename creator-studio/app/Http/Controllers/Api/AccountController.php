<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Auth\TwoFactor;
use App\Domains\Security\Audit;
use App\Domains\Security\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureFreshPassword;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everyone's own account (Role ability `account`): profile, password, email, two-factor
 * sign-in, and, for clients, deleting their account. Anything sensitive asks for the
 * current password again.
 */
class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->toSession() + [
                'phone' => $user->phone,
                'recovery_codes_left' => $user->hasTwoFactor() ? TwoFactor::recoveryCodesLeft($user) : null,
                'can_delete' => !$user->isAdmin(),
            ],
        ]);
    }

    /**
     * Whether the password was typed in the last few minutes (sensitive actions need that).
     */
    public function passwordFresh(Request $request): JsonResponse
    {
        return response()->json([
            'fresh' => EnsureFreshPassword::fresh($request),
        ]);
    }

    public function confirmPasswordNow(Request $request): JsonResponse
    {
        $this->confirmPassword($request->user(), (string)$request->input('password'));
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json([
            'ok' => true,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:40',
            'notify_email' => 'sometimes|boolean',
        ]);
        $request->user()->update($data);

        return response()->json([
            'message' => 'Saved.',
            'user' => $request->user()->toSession(),
        ]);
    }

    /**
     * New password: every other device is signed out.
     */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', AuthController::passwordRule()],
        ]);
        $user = $request->user();
        $this->confirmPassword($user, $data['current_password']);

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();
        AuthController::endSessions($user, $request->session()->getId());
        Audit::log('password_changed', $user);

        return response()->json([
            'message' => 'Password changed. Other devices have been signed out.',
        ]);
    }

    /**
     * A client's new email must be confirmed again before they see anything.
     */
    public function email(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'required|string',
        ]);
        $this->confirmPassword($user, $data['current_password']);

        $user->forceFill([
            'email' => strtolower(trim($data['email'])),
        ] + ($user->isAdmin() ? [] : [
            'email_verified_at' => null,
        ]))->save();
        if (!$user->isAdmin()) {
            rescue(fn () => $user->sendEmailVerificationNotification(), null, true);
        }
        Audit::log('email_changed', $user);

        return response()->json([
            'message' => $user->isAdmin() ? 'Email changed.' : "Email changed. Open the confirmation link we sent to {$user->email} to keep going.",
            'user' => $user->toSession(),
        ]);
    }

    /**
     * Step 1 of turning on two-factor: a new secret for the authenticator app.
     */
    public function twoFactorStart(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            // Replacing a working second factor needs the password again.
            $this->confirmPassword($user, (string)$request->input('current_password'));
        }
        $secret = TwoFactor::start($user);

        return response()->json([
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => TwoFactor::uri($user, $secret),
        ]);
    }

    /**
     * Step 2: the first code from the app turns it on and shows the recovery codes once.
     */
    public function twoFactorConfirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:10',
        ]);
        $user = $request->user();
        $codes = TwoFactor::confirm($user, str_replace(' ', '', $data['code']));
        if ($codes === null) {
            throw ValidationException::withMessages([
                'code' => 'That code didn’t work. Check the time on your phone and try the newest code.',
            ]);
        }

        Audit::log('two_factor_on', $user);

        return response()->json([
            'message' => 'Two-factor sign-in is on.',
            'recovery_codes' => $codes,
            'user' => $user->toSession(),
        ]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 409, 'Turn on two-factor sign-in first.');
        $this->confirmPassword($user, (string)$request->input('current_password'));

        Audit::log('recovery_codes', $user);

        return response()->json([
            'recovery_codes' => TwoFactor::newRecoveryCodes($user),
        ]);
    }

    /**
     * Clients may turn it off; admins can't (Role::requiresTwoFactor).
     */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role()->requiresTwoFactor()) {
            return response()->json([
                'error' => 'Admins always sign in with two-factor. You can set up a new phone instead.',
            ], 403);
        }
        $this->confirmPassword($user, (string)$request->input('current_password'));
        TwoFactor::disable($user);
        Audit::log('two_factor_off', $user);

        return response()->json([
            'message' => 'Two-factor sign-in is off.',
            'user' => $user->toSession(),
        ]);
    }

    /**
     * A client deletes their login. Her records of the work stay (they just aren't linked).
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return response()->json([
                'error' => 'The studio’s admin account can’t be deleted here.',
            ], 403);
        }
        $this->confirmPassword($user, (string)$request->input('current_password'));

        AgentEvent::log('lead', "{$user->name} ({$user->email}) deleted their client account.");
        Audit::log('account_deleted', $user, null, [
            'email' => $user->email,
        ]);
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Your account has been deleted.',
        ]);
    }

    /**
     * Where this account is signed in (with the database session driver), and its recent security events.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $request->session()->getId();
        $sessions = config('session.driver') === 'database'
            ? DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->orderByDesc('last_activity')->limit(20)->get()
                ->map(fn ($s) => [
                    'current' => $s->id === $current,
                    'browser' => Audit::browser((string)$s->user_agent),
                    'ip' => $s->ip_address,
                    'last_active' => date(DATE_ATOM, (int)$s->last_activity),
                ])->values()
            : [];

        return response()->json([
            'sessions' => $sessions,
            'events' => AuditLog::where('user_id', $user->id)->latest('created_at')->latest('id')->limit(15)->get()->map->toRow()->values(),
        ]);
    }

    /**
     * Sign out everywhere else (a lost laptop, a shared computer).
     */
    public function endOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->confirmPassword($user, (string)$request->input('current_password'));
        AuthController::endSessions($user, $request->session()->getId());
        Audit::log('sessions_ended', $user);

        return response()->json([
            'message' => 'Signed out on every other device.',
        ]);
    }

    /**
     * GDPR Article 20: Right to Data Portability.
     * Provides a structured, machine-readable JSON archive of all personal data,
     * account details, brand vault assets, deals, and audit records.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'export_metadata' => [
                'standard' => 'GDPR Article 20 (Right to Data Portability)',
                'regulation' => 'Regulation (EU) 2016/679',
                'exported_at' => now()->toIso8601String(),
                'user_id' => $user->id,
                'data_controller' => config('app.name', 'Delia | Creator Studio'),
            ],
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'phone' => $user->phone,
                'role' => $user->role()->value,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'referral_code' => $user->referral_code,
                'referral_credits' => (int)($user->referral_credits ?? 0),
            ],
            'brand_vault' => $user->brandVault ? $user->brandVault->toResponse() : null,
            'deals' => $user->deals()->with(['files', 'messages', 'annotations'])->get()->map(function ($deal) {
                return [
                    'id' => $deal->id,
                    'brand_name' => $deal->brand_name,
                    'contact_name' => $deal->contact_name,
                    'contact_email' => $deal->contact_email,
                    'stage' => $deal->stage,
                    'rate' => $deal->rate,
                    'currency' => $deal->currency,
                    'deliverables' => $deal->deliverables,
                    'selected_tier' => $deal->selected_tier,
                    'add_ons' => $deal->add_ons,
                    'payment_status' => $deal->payment_status,
                    'signature_hash' => $deal->signature_hash,
                    'signer_email' => $deal->signer_email,
                    'signer_ip' => $deal->signer_ip,
                    'accepted_at' => $deal->accepted_at?->toIso8601String(),
                    'created_at' => $deal->created_at?->toIso8601String(),
                    'files' => $deal->files->map(fn ($f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'file_type' => $f->file_type,
                        'size' => $f->size,
                        'created_at' => $f->created_at?->toIso8601String(),
                    ]),
                    'annotations' => $deal->annotations->map(fn ($a) => [
                        'id' => $a->id,
                        'file_id' => $a->file_id,
                        'timestamp_seconds' => $a->timestamp_seconds,
                        'slide_number' => $a->slide_number,
                        'content' => $a->content,
                        'is_resolved' => (bool)$a->is_resolved,
                        'created_at' => $a->created_at?->toIso8601String(),
                    ]),
                    'messages' => $deal->messages->map(fn ($m) => [
                        'id' => $m->id,
                        'body' => $m->body,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
                ];
            }),
            'audit_log' => AuditLog::where('user_id', $user->id)->latest('created_at')->limit(50)->get()->map->toRow(),
        ];

        Audit::log('gdpr_export', $user);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="gdpr-data-export-user-' . $user->id . '.json"',
        ]);
    }

    private function confirmPassword(User $user, string $password): void
    {
        if ($password === '' || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That isn’t your current password.',
            ]);
        }
    }
}
