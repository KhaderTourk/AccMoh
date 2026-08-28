<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('super.login');
        }

        $user = Auth::user();
        if (! $user->is_active || ! $user->is_platform_admin) {
            Auth::logout();

            return redirect()->route('super.login')->withErrors(['email' => 'هذه اللوحة لمدير المنصة فقط.']);
        }

        return $next($request);
    }
}
