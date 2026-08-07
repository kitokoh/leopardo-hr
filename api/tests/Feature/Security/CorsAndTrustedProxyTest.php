<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * See docs/security/AUDIT_API_2026-07-19.md, sections 4-5.
 */
class CorsAndTrustedProxyTest extends TestCase
{
    public function test_cors_allowed_headers_are_an_explicit_list_not_wildcard(): void
    {
        $allowedHeaders = config('cors.allowed_headers');

        $this->assertIsArray($allowedHeaders);
        $this->assertNotContains('*', $allowedHeaders);
        $this->assertContains('Authorization', $allowedHeaders);
        $this->assertContains('Content-Type', $allowedHeaders);
    }

    public function test_cors_allowed_origins_never_contain_wildcard_while_credentials_are_supported(): void
    {
        $this->assertTrue((bool) config('cors.supports_credentials'));

        foreach (config('cors.allowed_origins') as $origin) {
            $this->assertNotSame('*', $origin);
        }
    }

    public function test_cors_allows_the_real_vitrine_origin(): void
    {
        // The live vitrine deployment is gestionemployer-backend.vercel.app
        // (leopardo-hr.vercel.app returns 404 — see front/web/.env.local.example).
        // Without it, any direct browser API call from the vitrine is silently
        // CORS-blocked. See issue #1468.
        $this->assertContains('https://gestionemployer-backend.vercel.app', config('cors.allowed_origins'));
    }

    public function test_request_trusts_x_forwarded_for_from_the_render_edge_proxy(): void
    {
        // Render is the single edge proxy in front of this app; the request's
        // resolved client IP must come from X-Forwarded-For (not the proxy's
        // own connecting IP) once trustProxies() is configured, otherwise
        // per-IP rate limiting is effectively keyed on the proxy's IP for
        // every request.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->getJson('/api/v1/health/live');

        $response->assertOk();
        $this->assertSame('203.0.113.7', request()->ip());
    }
}
