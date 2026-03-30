<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestrictedAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedRoles = ['admin', 'owner', 'manager'];
        $role = strtolower((string) $request->query('role', ''));

        if (!in_array($role, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
