<?php

namespace App\Http\Middleware;

use App\Models\GateTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGateTerminal
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Gate terminal credentials are required.'], 401);
        }

        $terminal = GateTerminal::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($terminal === null || ! hash_equals($terminal->api_token, $token)) {
            return response()->json(['message' => 'Gate terminal credentials are invalid or revoked.'], 401);
        }

        $terminal->update(['last_seen_at' => now()]);
        $request->attributes->set('gateTerminal', $terminal);

        return $next($request);
    }
}
