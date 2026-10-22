<?php

namespace App\Http\Middleware;

use App\Support\ArchiveVisibility;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token (Sanctum) API equivalent of EnsureCpAuth: binds the tenant of the
 * authenticated user so tenant-scoped models never leak across tenants.
 */
class EnsureApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'الحساب غير مفعّل.'], 403);
        }

        if ($user->is_platform_admin) {
            return response()->json(['message' => 'حساب المنصة لا يستخدم واجهة النسخ.'], 403);
        }

        $tenant = $user->tenant;
        if (! $user->tenant_id || ! $tenant) {
            return response()->json(['message' => 'الحساب غير مرتبط بنسخة نظام.'], 403);
        }
        if (! $tenant->is_active) {
            return response()->json(['message' => 'نسخة النظام موقوفة. تواصل مع الإدارة.'], 403);
        }

        TenantContext::set((int) $user->tenant_id);
        $request->attributes->set('tenant', $tenant);

        // Per-request equivalent of the CP "show archived" toggle.
        if ($request->boolean('show_archived')) {
            session()->put(ArchiveVisibility::SESSION_KEY, true);
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
