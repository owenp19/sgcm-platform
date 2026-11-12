<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

class PublicEndpointProtectionTest extends SecurityTestCase
{
    private function middlewareFor(string $method, string $uri): array
    {
        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if ($route->uri() === ltrim($uri, '/') && in_array($method, $route->methods(), true)) {
                return $route->gatherMiddleware();
            }
        }

        $this->fail("Ruta {$method} {$uri} no encontrada");
    }

    public function test_busqueda_publica_de_expedientes_tiene_rate_limit(): void
    {
        $this->assertContains('throttle:api', $this->middlewareFor('POST', 'api/requests/search-record'));
    }

    public function test_validacion_publica_de_qr_tiene_rate_limit(): void
    {
        $this->assertContains('throttle:api', $this->middlewareFor('POST', 'api/certificates/validate-qr'));
    }

    public function test_recibo_turistico_usa_limite_especifico(): void
    {
        $this->assertContains('throttle:tourism-card', $this->middlewareFor('GET', 'api/tourism-card/card/{code}/receipt'));
    }
}
