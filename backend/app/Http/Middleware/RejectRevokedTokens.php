<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Revoca tokens JWT emitidos antes de un cambio/restablecimiento de contraseña.
 *
 * La marca se guarda en caché (no requiere migraciones). En despliegues con
 * varias instancias se debe usar un almacén compartido (Redis/DB) para que la
 * revocación sea consistente entre nodos.
 */
class RejectRevokedTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $revokedBefore = Cache::get("auth.tokens_revoked_before.{$user->id}");

        if ($revokedBefore === null) {
            return $next($request);
        }

        try {
            $issuedAt = (int) JWTAuth::parseToken()->getPayload()->get('iat');
        } catch (\Throwable) {
            return response()->json(['error' => 'Token no válido o expirado'], 401);
        }

        if ($issuedAt <= (int) $revokedBefore) {
            return response()->json(['error' => 'Token no válido o expirado'], 401);
        }

        return $next($request);
    }
}
