<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Global security response headers (issue #1469). See
 * app/Http/Middleware/SecurityHeaders.php.
 */
class SecurityHeadersTest extends TestCase
{
    /** @test */
    public function api_responses_carry_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    /** @test */
    public function hsts_is_sent_only_over_https(): void
    {
        // Deterministic scheme: use ABSOLUTE URLs with an explicit scheme.
        // A relative path would be resolved through url()/APP_URL, so a
        // deployment whose APP_URL is https:// (e.g. the Render backend)
        // would make the "plain HTTP" request secure and the assertion
        // below would fail depending on the ambient APP_URL/APP_ENV.
        //
        // Run the plain request first: withHeaders()/withServerVariables()
        // below would otherwise leak X-Forwarded-Proto onto it.
        $plain = $this->getJson('http://localhost/api/v1/health/live');
        $plain->assertOk();
        $plain->assertHeaderMissing('Strict-Transport-Security');

        // Over HTTPS the request is secure → HSTS is advertised.
        $secure = $this->getJson('https://localhost/api/v1/health/live');
        $secure->assertOk();
        $secure->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Through the trusted Render proxy (private peer + X-Forwarded-Proto:
        // https) the request is also considered secure, even over plain HTTP.
        $proxied = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/api/v1/health/live');

        $proxied->assertOk();
        $proxied->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
