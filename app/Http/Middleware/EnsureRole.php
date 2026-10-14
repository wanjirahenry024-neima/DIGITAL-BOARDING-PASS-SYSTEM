<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class EnsureRole
{
    protected function authorizeRole(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isActive() && $user->role === $role, 403);

        return $next($request);
    }
}
