<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #2625 — la route POST /webhooks/{webhookEndpoint}/test était déclarée
 * DEUX fois dans routes/modules/hr_extended.php (l.168 et l.176) : la
 * première gagnait, la seconde était morte (bruit + risque de dérive).
 * Garde : exactement une occurrence de chaque route webhook critique.
 */
class WebhookRoutesUniquenessTest extends TestCase
{
    public function test_webhook_test_route_is_declared_once(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'api/v1/webhooks/{webhookEndpoint}/test')
            ->filter(fn ($route) => in_array('POST', $route->methods(), true));

        $this->assertCount(1, $routes, 'POST /api/v1/webhooks/{webhookEndpoint}/test doit être déclarée exactement une fois.');
    }

    public function test_webhook_routes_are_declared_once(): void
    {
        // Une paire (URI, méthode) ne doit apparaître qu'une seule fois.
        $pairs = [
            ['api/v1/webhooks', 'GET'],
            ['api/v1/webhooks', 'POST'],
            ['api/v1/webhooks/{webhookEndpoint}', 'GET'],
            ['api/v1/webhooks/{webhookEndpoint}', 'PUT'],
            ['api/v1/webhooks/{webhookEndpoint}', 'DELETE'],
            ['api/v1/webhooks/{webhookEndpoint}/dead-letters', 'GET'],
            ['api/v1/webhooks/{webhookEndpoint}/dead-letters/{delivery}/replay', 'POST'],
        ];

        foreach ($pairs as [$uri, $method]) {
            $count = collect(app('router')->getRoutes()->getRoutes())
                ->filter(fn ($route) => $route->uri() === $uri)
                ->filter(fn ($route) => in_array($method, $route->methods(), true))
                ->count();
            $this->assertSame(1, $count, "La route {$method} {$uri} doit être déclarée exactement une fois (trouvé {$count}).");
        }
    }
}
