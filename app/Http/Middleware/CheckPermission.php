<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('cp.login');
        }
        if (!$user->canAccess($permission)) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }
        return $next($request);
    }
}
