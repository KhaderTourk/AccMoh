<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check() && Auth::user()->is_platform_admin) {
            return redirect()->route('super.dashboard');
        }

        return view('super.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])->onlyInput('email');
        }

        $user = Auth::user();
        if (! $user->is_active || ! $user->is_platform_admin) {
            Auth::logout();

            return back()->withErrors(['email' => 'هذه اللوحة لمدير المنصة فقط.']);
        }

        $request->session()->regenerate();

        return redirect()->route('super.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super.login');
    }
}
