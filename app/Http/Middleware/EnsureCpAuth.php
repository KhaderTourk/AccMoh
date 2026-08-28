<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
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

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            if ($request->expectsJson() || $request->is('cp/api/*')) {
                return response()->json(['message' => 'الحساب غير مفعّل.'], 403);
            }

            return redirect()->route('cp.login')->withErrors(['email' => 'حسابك غير مفعّل.']);
        }

        // Platform admins use /super only — never the tenant CP
        if ($user->is_platform_admin) {
            if ($request->expectsJson() || $request->is('cp/api/*')) {
                return response()->json(['message' => 'حساب المنصة لا يدخل لوحة AccMa.'], 403);
            }

            return redirect()->route('super.dashboard');
        }

        if (! $user->tenant_id) {
            Auth::logout();

            return redirect()->route('cp.login')->withErrors(['email' => 'الحساب غير مرتبط بنسخة نظام.']);
        }

        $tenant = $user->tenant;
        if (! $tenant || ! $tenant->is_active) {
            Auth::logout();

            return redirect()->route('cp.login')->withErrors(['email' => 'نسخة النظام موقوفة. تواصل مع الإدارة.']);
        }

        TenantContext::set((int) $user->tenant_id);
        $request->attributes->set('tenant', $tenant);

        $response = $next($request);
        TenantContext::clear();

        return $response;
    }
}
