<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $apiRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->getPrefix() === 'api');

    $phpFull = explode('.', PHP_VERSION);

    return view('api.index', [
        'appName' => config('app.name', 'SGCM API'),
        'appEnv' => config('app.env'),
        'framework' => 'Laravel '.app()->version().' · PHP '.PHP_VERSION,
        'phpVersion' => $phpFull[0].'.'.$phpFull[1],
        'endpointCount' => $apiRoutes->count(),
        'controllerCount' => count(glob(app_path('Http/Controllers/Api/*.php'))),
        'year' => date('Y'),
        'generatedAt' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm'),
    ]);
});

// Dashboard de estado del sistema (pagina HTML siempre)
Route::get('/status', [HealthController::class, 'status']);