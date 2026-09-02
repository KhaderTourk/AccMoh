<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCpPermission
{
    protected array $routeToPermission = [
        'cp.users' => 'users',
        'cp.roles' => 'users',
        'cp.balances' => 'finance',
        'cp.clients' => 'finance',
        'cp.client-services' => 'finance',
        'cp.payments' => 'finance',
        'cp.family-members' => 'finance',
        'cp.family-loans' => 'finance',
        'cp.expenses' => 'finance',
        'cp.transfers' => 'finance',
        'cp.ledger' => 'finance',
        'cp.reports' => 'finance',
        'cp.service-types' => 'finance',
        'cp.expense-categories' => 'finance',
        'cp.workers' => 'finance',
        'cp.suppliers' => 'finance',
        'cp.vendor-charges' => 'finance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        if (!$routeName || !str_starts_with($routeName, 'cp.')) {
            return $next($request);
        }

        $permission = $this->resolvePermission($routeName);
        if (!$permission) {
            return $next($request);
        }

        $user = Auth::user();
        if (!$user || !$user->canAccess($permission)) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }

    protected function resolvePermission(string $routeName): ?string
    {
        if ($routeName === 'cp.dashboard') {
            return null;
        }
        foreach ($this->routeToPermission as $prefix => $permission) {
            if (str_starts_with($routeName, $prefix)) {
                return $permission;
            }
        }
        return null;
    }
}
