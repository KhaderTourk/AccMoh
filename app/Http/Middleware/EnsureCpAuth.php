<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCpAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson() || $request->is('cp/api/*')) {
                return response()->json(['message' => 'غير مصرح.'], 401);
            }

            return redirect()->route('cp.login')->with('intended', $request->url());
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            if ($request->expectsJson() || $request->is('cp/api/*')) {
                return response()->json(['message' => 'الحساب غير مفعّل.'], 403);
            }

            return redirect()->route('cp.login')->withErrors(['email' => 'حسابك غير مفعّل.']);
        }

        return $next($request);
    }
}
