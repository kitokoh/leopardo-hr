<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationCatalogApiTest extends TestCase
{
    public function test_versions_endpoint_exposes_supported_locales(): void
    {
        $response = $this->getJson('/api/v1/i18n/catalog');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_locale', 'fr')
            ->assertJsonPath('data.supported_variants.en.1', 'en-GB');
    }

    public function test_locale_endpoint_normalizes_locale_variants(): void
    {
        $response = $this->getJson('/api/v1/i18n/catalog/fr-CA');

        $response->assertOk()
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.rtl', false)
            ->assertJsonPath('data.catalog._locale', 'fr');
    }

    public function test_locale_endpoint_supports_conditional_etag_requests(): void
    {
        $first = $this->getJson('/api/v1/i18n/catalog/ar-SA');
        $etag = $first->headers->get('ETag');

        $this->withHeaders([
            'If-None-Match' => $etag,
        ])->get('/api/v1/i18n/catalog/ar-SA')
            ->assertStatus(304);
    }

    public function test_catalog_has_its_own_rate_limit_bucket(): void
    {
        // Issue #4501 : le catalogue i18n partageait le bucket auth-sensitive
        // (10/min par email|IP) avec le login — un échec de login affamait les
        // traductions de la UI et vice-versa (self-DoS derrière NAT).
        // Désormais sur son propre bucket public-registry.
        config(['security.rate_limits.auth_per_minute' => 2]);

        $payload = [
            'email' => 'catalog-bucket@example.test',
            'password' => 'wrong-password',
            'device_name' => 'test-suite',
        ];

        // Épuise le bucket auth-sensitive (3e appel → 429).
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);

        // Le catalogue répond toujours : bucket indépendant.
        $this->getJson('/api/v1/i18n/catalog')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
