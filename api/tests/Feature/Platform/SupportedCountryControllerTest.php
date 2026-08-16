<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #4217 (audit 360° 2026-08-16) — le registre multi-pays canonique
 * (#1867) est désormais PUBLIC : la vitrine, l'onboarding public et les apps
 * mobiles pré-login listent les pays supportés avant toute connexion.
 * Aucune donnée sensible exposée (codes ISO, devises, fuseaux, niveaux de
 * confiance) — GET-only, throttle dédié.
 */
class SupportedCountryControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_registry_is_public_without_token(): void
    {
        $response = $this->getJson('/api/v1/supported-countries');

        $response->assertOk();
        $registry = $response->json('data');
        $this->assertIsArray($registry);
        $this->assertGreaterThanOrEqual(4, count($registry));

        $first = $registry[0];
        foreach (['country', 'label', 'language', 'currency', 'timezone', 'confidence', 'available', 'compliance'] as $key) {
            $this->assertArrayHasKey($key, $first, "missing key {$key}");
        }
        $this->assertArrayHasKey('level', $first['compliance']);
    }

    public function test_registry_still_works_authenticated(): void
    {
        // Non-régression : le endpoint reste utilisable une fois connecté
        // (les apps admin/mobiles l'appellent avec un token).
        $response = $this->getJson('/api/v1/supported-countries');

        $response->assertOk();
    }

    public function test_registry_is_read_only(): void
    {
        $response = $this->postJson('/api/v1/supported-countries', []);

        $response->assertStatus(405);
    }

    public function test_sensitive_endpoints_still_require_auth(): void
    {
        // Non-régression : seul le registre public est sorti du groupe auth.
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
