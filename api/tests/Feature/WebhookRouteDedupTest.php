<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2625) : POST /webhooks/{webhookEndpoint}/test
 * était déclaré deux fois (routes/modules/hr_extended.php:168 et :176) — la
 * définition unique est la source de vérité.
 */
class WebhookRouteDedupTest extends TestCase
{
    public function test_webhook_test_route_is_declared_once(): void
    {
        $routes = Route::getRoutes()->getRoutes();

        $matches = array_filter($routes, function ($route) {
            $uri = $route->uri();
            $methods = $route->methods();

            // URI exacte : le variant super-admin /admin/webhooks/... existe
            // aussi (surface distincte, #2634) et terminait pareil (#5034).
            return $uri === 'api/v1/webhooks/{webhookEndpoint}/test'
                && in_array('POST', $methods, true);
        });

        $this->assertCount(1, $matches);
    }

    public function test_trial_status_has_dedicated_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('v1.trial.status');

        if ($route === null) {
            // fallback : recherche par URI
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => $r->uri() === 'api/v1/trial/status');
        }

        $this->assertNotNull($route, 'GET /api/v1/trial/status route not found');

        $middleware = $route->gatherMiddleware();
        $this->assertContains('throttle:trial-status', $middleware);
        $this->assertNotContains('throttle:5,15', $middleware);
    }
}
