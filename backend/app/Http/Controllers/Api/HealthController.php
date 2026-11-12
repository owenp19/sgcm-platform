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

    /**
     * Datos de estado sin información interna: ni host, ni nombre de base de
     * datos, ni credenciales, ni versiones exactas, ni mensajes de excepción.
     */
    private function gatherChecks(): array
    {
        $db = $this->checkDatabase();
        $gemini = $this->checkGemini();

        $jsonStatus = $db['ok'] ? 'ok' : 'down';
        $pageLevel = $db['ok'] ? ($gemini['ok'] ? 'ok' : 'degraded') : 'down';

        $json = [
            'status' => $jsonStatus,
            'service' => 'SGCM API',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $db['ok'] ? 'ok' : 'error',
                'ai' => $gemini['ok'] ? 'ok' : ($gemini['configured'] ? 'error' : 'not_configured'),
            ],
        ];

        $view = [
            'level' => $pageLevel,
            'db' => [
                'label' => 'MySQL / MariaDB',
                'ok' => $db['ok'],
                'latencyMs' => $db['latencyMs'] ?? null,
                'host' => 'Protegido',
                'port' => null,
                'database' => 'Protegido',
                'message' => $db['ok'] ? null : 'No se pudo conectar a la base de datos.',
            ],
            'gemini' => [
                'ok' => $gemini['ok'],
                'configured' => $gemini['configured'],
                'label' => $gemini['label'],
                'model' => 'Protegido',
                'message' => $gemini['ok'] ? null : 'El asistente no está disponible en este momento.',
            ],
            'counts' => [],
            'generatedAt' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm'),
            'timezone' => date_default_timezone_get(),
            'appEnv' => 'Protegido',
            'debug' => false,
            'framework' => 'Laravel',
            'phpVersion' => 'Protegido',
            'phpFull' => 'Protegido',
            'apiBase' => url('/api'),
            'docsUrl' => url('api/documentation'),
        ];

        return compact('json', 'view');
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');

            return [
                'ok' => true,
                'latencyMs' => round((microtime(true) - $start) * 1000, 1),
            ];
        } catch (\Throwable) {
            return ['ok' => false];
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
                    'label' => 'No configurado',
                ];
            }

            try {
                app(GeminiService::class)->chat('Responde únicamente: OK');

                return [
                    'ok' => true,
                    'configured' => true,
                    'label' => 'Conectado',
                ];
            } catch (\Throwable) {
                // No propagar el mensaje de la excepción: puede contener
                // detalles del proveedor, endpoints o configuración.
                return [
                    'ok' => false,
                    'configured' => true,
                    'label' => 'Error de conexión',
                ];
            }
        });
    }
}