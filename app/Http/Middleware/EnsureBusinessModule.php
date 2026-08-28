<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant') ?? $request->user()?->tenant;

        if ($tenant && ! $tenant->business_enabled) {
            if ($request->expectsJson() || $request->is('cp/api/*')) {
                return response()->json(['message' => 'وحدة العمل غير مفعّلة لهذه النسخة.'], 403);
            }

            return redirect()->route('cp.dashboard')->with('error', 'وحدة العمل (الزبائن/الخدمات/الدفعات) غير مفعّلة لهذه النسخة.');
        }

        return $next($request);
    }
}
