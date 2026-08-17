<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA 2026-08-15 (#2653) — contrat d'erreur API unifié.
 *
 * Constat live : 401 en HTML redirect sans header Accept, 500 brut
 * `{"message":"Server Error"}`, 404 avec localized_message incohérents,
 * erreur onboarding imbriquée.
 */
class ApiErrorContractTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_unauthenticated_api_route_returns_json_401_without_accept_header(): void
    {
        // Sans header Accept, la réponse doit rester du JSON conforme au
        // contrat (jamais de redirect HTML vers /login).
        $response = $this->get('/api/v1/employees');

        $response->assertStatus(401);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type', ''));
        $response->assertJsonPath('error', 'UNAUTHENTICATED');
        $response->assertJsonPath('message', 'UNAUTHENTICATED');
        $this->assertNotNull($response->json('localized_message'));
    }

    public function test_unhandled_exception_returns_internal_error_shape(): void
    {
        Route::get('/api/v1/qa-test-throw', function (): never {
            throw new \RuntimeException('qa-boom');
        });

        $response = $this->getJson('/api/v1/qa-test-throw');

        $response->assertStatus(500);
        $response->assertJsonPath('error', 'INTERNAL_ERROR');
        $response->assertJsonPath('message', 'INTERNAL_ERROR');
        $this->assertNotNull($response->json('localized_message'));
        // Le message interne ne doit jamais fuiter.
        $response->assertJsonMissing(['message' => 'qa-boom']);
    }

    public function test_unknown_api_route_returns_flat_404_shape(): void
    {
        $response = $this->getJson('/api/v1/definitely-not-a-route-xyz');

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'RESOURCE_NOT_FOUND');
        $response->assertJsonPath('message', 'RESOURCE_NOT_FOUND');
        $this->assertIsString($response->json('localized_message'));
    }

    public function test_onboarding_invalid_token_returns_flat_error_shape(): void
    {
        $response = $this->getJson('/api/v1/onboarding/invitation/not-a-real-token');

        $response->assertStatus(404);
        // L'erreur doit être un code string plat, pas un objet imbriqué.
        $this->assertIsString($response->json('error'));
        $this->assertSame('INVITATION_NOT_FOUND', $response->json('error'));
        $this->assertSame('INVITATION_NOT_FOUND', $response->json('message'));
        $this->assertIsString($response->json('localized_message'));
    }

    public function test_i18n_catalog_unavailable_returns_503_shape(): void
    {
        // Simule un conteneur sans shared/i18n (constat prod 2026-08-15) :
        // le endpoint doit répondre 503 conforme, jamais une page HTML 500.
        File::shouldReceive('exists')->andReturn(false);

        $response = $this->getJson('/api/v1/i18n/catalog/fr');

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'I18N_CATALOG_UNAVAILABLE');
        $response->assertJsonPath('message', 'I18N_CATALOG_UNAVAILABLE');
    }

    // ── #4689 (audit 360° 2026-08-16) : abort() statique — localized_message
    // systématique sur 403/422/429 ────────────────────────────────────────

    public function test_abort_with_catalog_code_includes_localized_message(): void
    {
        Route::get('/api/v1/qa-test-abort-code', fn () => abort(403, 'FORBIDDEN'));

        $response = $this->getJson('/api/v1/qa-test-abort-code');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'FORBIDDEN');
        $response->assertJsonPath('message', 'FORBIDDEN');
        $response->assertJsonPath('localized_message', __('errors.FORBIDDEN'));
    }

    public function test_abort_with_arbitrary_message_includes_generic_localized_message(): void
    {
        Route::get('/api/v1/qa-test-abort-msg', fn () => abort(422, 'Manager role required.'));

        $response = $this->getJson('/api/v1/qa-test-abort-msg');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Manager role required.');
        $response->assertJsonPath('message', 'Manager role required.');
        // #4689 (implémentation main) : code mappé par statut → VALIDATION_FAILED.
        $response->assertJsonPath('localized_message', __('errors.VALIDATION_FAILED'));
    }

    public function test_abort_catalog_code_respects_accept_language(): void
    {
        // #4690 : abort() avec un code du catalogue → localized_message traduit
        // selon Accept-Language (EN ici), jamais un littéral FR.
        Route::get('/api/v1/qa-test-abort-i18n', fn () => abort(403, 'FORBIDDEN'));

        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/qa-test-abort-i18n');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'FORBIDDEN');
        $this->assertSame(__('errors.FORBIDDEN', [], 'en'), $response->json('localized_message'));
        $this->assertSame('You do not have permission for this action.', $response->json('localized_message'));
    }

    public function test_throttled_route_returns_429_with_localized_message(): void
    {
        // /api/v1/health est throttlé 60/min (#4689 : la forme 429 doit porter
        // localized_message + les headers Retry-After, issue #1774).
        for ($i = 0; $i < 61; $i++) {
            $this->getJson('/api/v1/health');
        }

        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(429);
        $this->assertIsString($response->json('localized_message'));
        $response->assertHeader('Retry-After');
    }
}
