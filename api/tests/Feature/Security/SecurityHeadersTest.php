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
        // Over HTTPS the request is considered secure through the trusted
        // Render proxy (X-Forwarded-Proto https).
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Plain HTTP (local dev) must not advertise HSTS.
        $plain = $this->getJson('/api/v1/health/live');
        $plain->assertOk();
        $plain->assertHeaderMissing('Strict-Transport-Security');
    }
}
