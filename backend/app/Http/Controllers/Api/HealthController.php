<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function health(Request $request)
    {
        $checks = $this->gatherChecks();

        if ($request->wantsJson()) {
            return response()->json($checks['json']);
        }

        return view('api.status', $checks['view']);
    }

    public function status()
    {
        return view('api.status', $this->gatherChecks()['view']);
    }

    private function gatherChecks(): array
    {
        $db = $this->checkDatabase();
        $gemini = $this->checkGemini();

        $phpParts = explode('.', PHP_VERSION);

        $jsonStatus = $db['ok'] ? 'ok' : 'down';
        $pageLevel = $db['ok'] ? ($gemini['ok'] ? 'ok' : 'degraded') : 'down';

        $json = [
            'status' => $jsonStatus,
            'service' => 'SGCM API',
            'timestamp' => now()->toIso8601String(),
            'versions' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'database' => [
                'connection' => $db['ok'] ? 'ok' : 'error',
                'latency_ms' => $db['latencyMs'] ?? null,
            ],
            'ai' => [
                'configured' => $gemini['configured'],
                'model' => (string) config('services.gemini.model'),
                'connection' => $gemini['ok'] ? 'ok' : 'error',
            ],
        ];

        $view = [
            'level' => $pageLevel,
            'db' => $db,
            'gemini' => $gemini,
            'counts' => $db['ok'] ? $this->getCounts() : [],
            'generatedAt' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm'),
            'timezone' => date_default_timezone_get(),
            'appEnv' => (string) config('app.env'),
            'debug' => (bool) config('app.debug'),
            'framework' => 'Laravel '.app()->version(),
            'phpVersion' => $phpParts[0].'.'.$phpParts[1],
            'phpFull' => PHP_VERSION,
            'apiBase' => url('/api'),
            'docsUrl' => url('api/documentation'),
        ];

        return compact('json', 'view');
    }

    private function checkDatabase(): array
    {
        $base = [
            'label' => 'MySQL / MariaDB',
            'host' => (string) config('database.connections.mysql.host'),
            'port' => (int) config('database.connections.mysql.port'),
            'database' => (string) config('database.connections.mysql.database'),
        ];

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $base['ok'] = true;
            $base['latencyMs'] = round((microtime(true) - $start) * 1000, 1);

            return $base;
        } catch (\Throwable) {
            $base['ok'] = false;
            $base['message'] = 'No se pudo conectar a la base de datos.';

            return $base;
        }
    }

    private function checkGemini(): array
    {
        return Cache::remember('sgcm.gemini.check', 60, function () {
            $configured = (bool) config('services.gemini.key');

            if (! $configured) {
                return [
                    'ok' => false,
                    'configured' => false,
                    'model' => (string) config('services.gemini.model'),
                    'label' => 'No configurado',
                    'message' => 'Define GEMINI_API_KEY en backend/.env',
                ];
            }

            try {
                app(GeminiService::class)->chat('Responde únicamente: OK');

                return [
                    'ok' => true,
                    'configured' => true,
                    'model' => (string) config('services.gemini.model'),
                    'label' => 'Conectado',
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'configured' => true,
                    'model' => (string) config('services.gemini.model'),
                    'label' => 'Error de conexión',
                    'message' => $e->getMessage(),
                ];
            }
        });
    }

    private function getCounts(): array
    {
        $specs = [
            ['key' => 'users', 'label' => 'Usuarios', 'table' => 'users'],
            ['key' => 'procedure_types', 'label' => 'Trámites', 'table' => 'procedure_types'],
            ['key' => 'requests', 'label' => 'Radicados', 'table' => 'requests'],
            ['key' => 'appointments', 'label' => 'Citas', 'table' => 'appointments'],
            ['key' => 'tourist_cards', 'label' => 'Tarjetas', 'table' => 'tourist_cards'],
            ['key' => 'announcements', 'label' => 'Avisos', 'table' => 'announcements'],
        ];

        try {
            return collect($specs)->map(function (array $spec) {
                return [
                    'key' => $spec['key'],
                    'label' => $spec['label'],
                    'count' => DB::table($spec['table'])->count(),
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }
}