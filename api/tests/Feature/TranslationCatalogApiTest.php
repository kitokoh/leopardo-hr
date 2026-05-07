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
}
