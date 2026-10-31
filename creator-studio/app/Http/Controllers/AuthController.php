<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (User::count() === 0) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $creds = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($creds, true)) {
            return back()->withErrors(['email' => 'That email and password don\'t match.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** First run only: create the one account this app has. */
    public function showSetup()
    {
        if (User::count() > 0) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function setup(Request $request)
    {
        abort_if(User::count() > 0, 403);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        if (! Setting::has('creator_name')) {
            Setting::put('creator_name', $data['name']);
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('settings.edit')->with('status', "Welcome, {$data['name']}! Start by filling in your channel and API keys.");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

