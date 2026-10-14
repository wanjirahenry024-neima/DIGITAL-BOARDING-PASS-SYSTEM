<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin extends EnsureRole
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->authorizeRole($request, $next, 'admin');
    }
}
