<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canAccess($permission)) {
            return response()->json(['message' => 'ليس لديك صلاحية لهذه العملية.'], 403);
        }

        return $next($request);
    }
}
