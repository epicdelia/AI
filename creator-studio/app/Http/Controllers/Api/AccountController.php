<?php

namespace App\Http\Controllers\Api;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Auth\TwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return response()->json(['user' => $user->toSession() + [
            'phone' => $user->phone,
            'recovery_codes_left' => $user->hasTwoFactor() ? TwoFactor::recoveryCodesLeft($user) : null,
            'can_delete' => ! $user->isAdmin(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:40',
        ]);
        $request->user()->update($data);

        return response()->json(['message' => 'Saved.', 'user' => $request->user()->toSession()]);
    }

    /** New password: every other device is signed out. */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', AuthController::passwordRule()],
        ]);
        $user = $request->user();
        $this->confirmPassword($user, $data['current_password']);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        AuthController::endSessions($user, $request->session()->getId());

        return response()->json(['message' => 'Password changed. Other devices have been signed out.']);
    }

    /** A client's new email must be confirmed again before they see anything. */
    public function email(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'required|string',
        ]);
        $this->confirmPassword($user, $data['current_password']);

        $user->forceFill(['email' => strtolower(trim($data['email']))] + ($user->isAdmin() ? [] : ['email_verified_at' => null]))->save();
        if (! $user->isAdmin()) {
            rescue(fn () => $user->sendEmailVerificationNotification(), null, true);
        }

        return response()->json([
            'message' => $user->isAdmin() ? 'Email changed.' : "Email changed. Open the confirmation link we sent to {$user->email} to keep going.",
            'user' => $user->toSession(),
        ]);
    }

    /** Step 1 of turning on two-factor: a new secret for the authenticator app. */
    public function twoFactorStart(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            // Replacing a working second factor needs the password again.
            $this->confirmPassword($user, (string) $request->input('current_password'));
        }
        $secret = TwoFactor::start($user);

        return response()->json([
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => TwoFactor::uri($user, $secret),
        ]);
    }

    /** Step 2: the first code from the app turns it on and shows the recovery codes once. */
    public function twoFactorConfirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:10']);
        $user = $request->user();
        $codes = TwoFactor::confirm($user, str_replace(' ', '', $data['code']));
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'That code didn’t work. Check the time on your phone and try the newest code.']);
        }

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
        $this->confirmPassword($user, (string) $request->input('current_password'));

        return response()->json(['recovery_codes' => TwoFactor::newRecoveryCodes($user)]);
    }

    /** Clients may turn it off; admins can't (Role::requiresTwoFactor). */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role()->requiresTwoFactor()) {
            return response()->json(['error' => 'Admins always sign in with two-factor. You can set up a new phone instead.'], 403);
        }
        $this->confirmPassword($user, (string) $request->input('current_password'));
        TwoFactor::disable($user);

        return response()->json(['message' => 'Two-factor sign-in is off.', 'user' => $user->toSession()]);
    }

    /** A client deletes their login. Her records of the work stay (they just aren't linked). */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return response()->json(['error' => 'The studio’s admin account can’t be deleted here.'], 403);
        }
        $this->confirmPassword($user, (string) $request->input('current_password'));

        AgentEvent::log('lead', "{$user->name} ({$user->email}) deleted their client account.");
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    private function confirmPassword(User $user, string $password): void
    {
        if ($password === '' || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'That isn’t your current password.']);
        }
    }
}
