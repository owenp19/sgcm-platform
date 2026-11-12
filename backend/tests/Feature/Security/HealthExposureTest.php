<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class HealthExposureTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evita llamadas reales a Gemini durante el test.
        config(['services.gemini.key' => null]);
        Cache::flush();
    }

    public function test_health_json_no_expone_configuracion_interna(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissingPath('database')
            ->assertJsonMissingPath('versions')
            ->assertJsonMissingPath('ai');

        $body = $response->getContent();

        foreach (['occre_test_db', '127.0.0.1', 'DB_PASSWORD', 'JWT_SECRET', 'GEMINI_API_KEY'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_pagina_status_no_expone_host_base_de_datos_ni_conteos(): void
    {
        $response = $this->get('/status');

        $response->assertOk();
        $body = $response->getContent();

        foreach (['occre_test_db', '127.0.0.1', '3306', 'Actividad en la plataforma'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $this->assertStringContainsString('Protegido', $body);
    }

    public function test_health_tiene_rate_limit(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/health'
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:api', $route->gatherMiddleware());
    }
}
