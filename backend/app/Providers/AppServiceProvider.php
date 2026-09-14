<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Equivalente al chatLimiter de Node: 30 peticiones por minuto por usuario
        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Límite general por IP: 60 peticiones/minuto para toda la API autenticada
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Endpoints de autenticación: protege contra fuerza bruta
        // (5 por minuto por IP para evitar abuso sobre credenciales)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Endpoints de creación/pago de la tarjeta de turismo (públicos y sensibles)
        RateLimiter::for('tourism-card', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
