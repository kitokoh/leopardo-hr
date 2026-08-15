<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #3497 — les callbacks SSO SAML/OIDC sont publics (réponse IdP) mais
 * non authentifiés : le callback SAML décode/parse du XML, les flux OIDC
 * font du fetch config-driven. Un throttle 10/min par IP borne le DoS par
 * parsing XML et le brute-force sans casser les retries IdP légitimes.
 */
class SsoCallbackThrottleTest extends TestCase
{
    public function test_saml_callback_route_carries_throttle(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/sso/saml/{companyId}/callback' && in_array('POST', $r->methods(), true));

        $this->assertNotNull($route, 'Route POST /sso/saml/{companyId}/callback introuvable.');
        $this->assertStringContainsString(
            'throttle:10,1',
            implode(',', $route->gatherMiddleware()),
            'Le callback SAML public doit porter throttle:10,1.'
        );
    }

    public function test_oidc_authorize_route_carries_throttle(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/sso/oidc/{companyId}/authorize' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($route, 'Route GET /sso/oidc/{companyId}/authorize introuvable.');
        $this->assertStringContainsString(
            'throttle:10,1',
            implode(',', $route->gatherMiddleware()),
            'L\'authorize OIDC public doit porter throttle:10,1.'
        );
    }

    public function test_oidc_callback_route_carries_throttle(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/sso/oidc/{companyId}/callback' && in_array('GET', $r->methods(), true));

        $this->assertNotNull($route, 'Route GET /sso/oidc/{companyId}/callback introuvable.');
        $this->assertStringContainsString(
            'throttle:10,1',
            implode(',', $route->gatherMiddleware()),
            'Le callback OIDC public doit porter throttle:10,1.'
        );
    }
}
