<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'jwt.fresh' => \App\Http\Middleware\RejectRevokedTokens::class,
        ]);

        // API pura: nunca redirigir a rutas web; siempre 401 JSON
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Formato de error idéntico al backend Node: { "error": "mensaje" }
        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['error' => 'Token no válido o expirado'], 401);
            }

            // Formato idéntico al middleware validate.js del backend Node
            if ($e instanceof ValidationException) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => collect($e->errors())
                        ->map(fn ($messages, $field) => [
                            'field' => $field,
                            'message' => $messages[0],
                        ])
                        ->values()
                        ->all(),
                ], 400);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['error' => 'Recurso no encontrado'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                /** @var HttpException $e */
                $messages = [
                    400 => 'Solicitud inválida',
                    403 => 'No tienes permisos para realizar esta acción',
                    404 => 'Recurso no encontrado',
                    405 => 'Método no permitido',
                    409 => 'Conflicto con el estado actual',
                    422 => 'Datos inválidos',
                    429 => 'Demasiadas solicitudes, intenta más tarde',
                    500 => 'Error interno del servidor',
                    502 => 'Error de servicio externo',
                    503 => 'Servicio no disponible',
                ];

                $message = $e->getMessage() ?: ($messages[$e->getStatusCode()] ?? 'Error');

                return response()->json(
                    ['error' => $message],
                    $e->getStatusCode(),
                    $e->getHeaders()
                );
            }

            report($e);

            return response()->json(['error' => 'Error interno del servidor'], 500);
        });
    })->create();
