<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

/**
 * Régression QA live 2026-08-15 : le pattern CORS `https://*.pages.dev`
 * (glob nu) était passé tel quel à preg_match() par fruitcake/php-cors →
 * 500 sur TOUT origin non listé en dur (previews Cloudflare Pages).
 */
class CorsPreviewOriginTest extends TestCase
{
    public function test_pages_dev_preview_origin_gets_cors_headers_not_500(): void
    {
        // #5582 : le pattern est restreint au projet CONNU (leo-admin) — la
        // preview d'un projet arbitraire ne reçoit plus d'en-têtes CORS.
        $response = $this->getJson('/api/v1/health', [
            'Origin' => 'https://preview-abc123.leo-admin.pages.dev',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://preview-abc123.leo-admin.pages.dev');
    }

    public function test_arbitrary_pages_dev_project_gets_no_cors_headers(): void
    {
        // Un attaquant peut créer `quelconque.pages.dev` — aucun ACAO (#5582).
        $response = $this->getJson('/api/v1/health', [
            'Origin' => 'https://evil-arbitrary.pages.dev',
        ]);

        $response->assertStatus(200);
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_exact_whitelisted_origin_still_works(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'Origin' => 'https://leo-admin.pages.dev',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://leo-admin.pages.dev');
    }
}
