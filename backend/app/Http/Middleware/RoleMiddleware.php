<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Uso: ->middleware('role:admin') o ->middleware('role:funcionario,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Token no válido o expirado'], 401);
        }

        if (! $user->hasRole(...$roles)) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        return $next($request);
    }
}
