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
    public function test_cors_headers_explicit(): void
    {
        $allowedHeaders = config('cors.allowed_headers');

        $this->assertIsArray($allowedHeaders);
        $this->assertNotContains('*', $allowedHeaders);
        $this->assertContains('Authorization', $allowedHeaders);
        $this->assertContains('Content-Type', $allowedHeaders);
    }

    public function test_cors_origins_never_wildcard(): void
    {
        $this->assertTrue((bool) config('cors.supports_credentials'));

        foreach (config('cors.allowed_origins') as $origin) {
            $this->assertNotSame('*', $origin);
        }
    }

    public function test_cors_whitelist_includes_admin_dev_servers(): void
    {
        // Issue #1769 : le dev server du dashboard admin (Vite, port 3001)
        // appelle l'API en URL absolue (hors proxy) → il doit être whitelisté,
        // sinon le navigateur bloque le login en local (seul localhost:3000
        // via FRONTEND_URL était autorisé). Ajouté dans #1785, verrouillé ici
        // par un test de non-régression.
        $origins = config('cors.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertContains('http://localhost:3000', $origins);
        $this->assertContains('http://localhost:3001', $origins);
    }
    public function test_trusts_x_forwarded_for(): void
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
