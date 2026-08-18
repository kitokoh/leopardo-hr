<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #4955 (audit PM 2026-08-17) — les réponses 429 de throttle doivent
 * suivre le contrat API standard (error/message/localized_message) avec un
 * code stable + message localisé, au lieu du « Too Many Attempts. » brut de
 * Laravel.
 */
class RateLimit429LocalizedTest extends TestCase
{
    public function test_throttled_response_uses_standard_localized_shape(): void
    {
        // GET /api/v1/demo-users porte throttle:10,1 — la 11e requête de la
        // minute (même IP) déclenche le 429.
        $response429 = null;
        for ($i = 0; $i < 15; $i++) {
            $response = $this->getJson('/api/v1/demo-users');
            if ($response->getStatusCode() === 429) {
                $response429 = $response;
                break;
            }
        }

        $this->assertNotNull($response429, 'Aucune réponse 429 obtenue après 15 requêtes sur throttle:10,1.');

        $body = $response429->json();
        $this->assertSame('TOO_MANY_REQUESTS', $body['error']);
        $this->assertSame('TOO_MANY_REQUESTS', $body['message']);
        $this->assertIsString($body['localized_message']);
        // Le message localisé ne doit PAS être la clé brute (catalogue absent).
        $this->assertNotSame('errors.TOO_MANY_REQUESTS', $body['localized_message']);
        // Les headers de throttling doivent être préservés (#1774).
        $this->assertNotNull($response429->headers->get('Retry-After'));
    }
}
