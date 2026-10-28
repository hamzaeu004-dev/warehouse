<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            ActivityLogger::record('account.login_failed', "Failed login attempt for {$credentials['email']} from IP {$request->ip()}");

            return back()->withErrors(['email' => 'Incorrect email or password.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        ActivityLogger::record('account.login', 'Logged in from IP '.$request->ip().' ('.$this->device($request).')');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLogger::record('account.logout', 'Logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // Rough device name from the browser's user agent, enough to tell phone from PC
    private function device(Request $request): string
    {
        $agent = (string) $request->userAgent();

        return match (true) {
            str_contains($agent, 'Android') => 'Android phone',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Windows') => 'Windows PC',
            str_contains($agent, 'Macintosh') => 'Mac',
            default => 'unknown device',
        };
    }
}
