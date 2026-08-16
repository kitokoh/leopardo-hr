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

    public function test_registry_warning_language_is_consistent_per_country(): void
    {
        // #4446 : le champ brut `warning` doit être dans la langue des règles
        // (EN pour pilot/placeholder/unknown — source docs), indépendamment de
        // la locale de la requête ; `warning_localized` porte la localisation.
        $response = $this->getJson('/api/v1/supported-countries')->assertOk();
        $registry = $response->json('data');
        $this->assertNotEmpty($registry);

        $frenchWarnings = 0;
        foreach ($registry as $entry) {
            $this->assertIsString($entry['compliance']['warning']);
            $this->assertIsString($entry['compliance']['warning_localized']);
            // Le warning brut ne doit plus être un littéral FR (régression #4446).
            if (str_contains($entry['compliance']['warning'], 'Pays sans règles')
                || str_contains($entry['compliance']['warning'], 'Règles de structure')
                || str_contains($entry['compliance']['warning'], 'Règles pilotes')) {
                $frenchWarnings++;
            }
        }
        $this->assertSame(0, $frenchWarnings, 'Aucun warning brut FR ne doit rester dans le registre public.');
    }

    public function test_english_countries_have_english_labels(): void
    {
        // #4446 : les pays déclarés `language: en` servent un label en anglais
        // (United States / United Kingdom), pas le libellé FR francisé.
        $response = $this->getJson('/api/v1/supported-countries')->assertOk();
        $byCode = collect($response->json('data'))->keyBy('country');

        $this->assertSame('United States', $byCode['US']['label'] ?? null, 'Label US doit être en anglais.');
        $this->assertSame('United Kingdom', $byCode['GB']['label'] ?? null, 'Label GB doit être en anglais.');
        $this->assertSame('en', $byCode['US']['language'] ?? null);
        $this->assertSame('en', $byCode['GB']['language'] ?? null);
    }

    public function test_registry_is_cacheable_with_etag(): void
    {
        // Issue #4502 : registre quasi-statique — Cache-Control public + ETag
        // pour que les apps mobiles pré-login ne rebrûlent pas le bucket
        // public-registry 60/min à chaque lancement.
        $response = $this->getJson('/api/v1/supported-countries');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'public, max-age=3600')
            ->assertHeader('Vary', 'Accept-Language')
            ->assertHeader('ETag');

        $etag = (string) $response->headers->get('ETag');
        $this->assertStringStartsWith('W/"', $etag);

        // Requête conditionnelle → 304 Not Modified (bucket préservé).
        $this->getJson('/api/v1/supported-countries', ['If-None-Match' => $etag])
            ->assertStatus(304);
    }
}
