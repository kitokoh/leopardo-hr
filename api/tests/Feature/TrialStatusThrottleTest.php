<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #2621 — GET /trial/status est pollé par la vitrine (~1 req/5 s) :
 * il doit sortir du throttle `5,15` (qui 429erait le polling) et disposer
 * d'un limit dédié 60/min/IP.
 */
class TrialStatusThrottleTest extends TestCase
{
    public function test_trial_status_allows_20_polls_per_minute(): void
    {
        // 20 polls successifs avec un token arbitraire : le endpoint répond
        // 404 (token invalide) mais JAMAIS 429 (limiteur dédié 60/min).
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/v1/trial/status?token='.str_repeat('a', 64));
            $this->assertNotEquals(429, $response->getStatusCode(), "Poll {$i} : ne doit pas être rate-limité.");
        }
    }

    public function test_trial_status_route_carries_dedicated_throttle(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/trial/status' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($route, 'Route GET /api/v1/trial/status introuvable.');
        $this->assertStringContainsString(
            'throttle:trial-status',
            implode(',', $route->gatherMiddleware()),
            'GET /trial/status doit porter le limiteur dédié throttle:trial-status.'
        );
    }
}
