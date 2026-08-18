<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * #4689 (audit 360° 2026-08-16) — toute réponse d'erreur HTTP doit porter la
 * forme standard `error` + `message` + `localized_message`, y compris pour les
 * messages statiques passés à abort() (avant : pas de localized_message dans
 * cette branche → formes incohérentes selon l'endpoint).
 */
class HttpErrorLocalizedMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::get('/_test/err-403', fn () => abort(403, 'FORBIDDEN'));
            Route::get('/_test/err-404', fn () => abort(404));
            Route::get('/_test/err-422', fn () => abort(422, 'Manager role required.'));
            Route::get('/_test/err-429', fn () => abort(429, 'Too Many Requests'));
        });
    }

    public function test_403_response_has_localized_message(): void
    {
        $this->getJson('/api/_test/err-403')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FORBIDDEN')
            ->assertJsonPath('message', 'FORBIDDEN')
            ->assertJsonPath('localized_message', __('errors.FORBIDDEN'));
    }

    public function test_404_response_has_localized_message(): void
    {
        $this->getJson('/api/_test/err-404')
            ->assertStatus(404)
            ->assertJsonPath('error', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('message', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('localized_message', __('errors.NOT_FOUND'));
    }

    public function test_422_static_message_keeps_contract_and_adds_localized_message(): void
    {
        $this->getJson('/api/_test/err-422')
            ->assertStatus(422)
            ->assertJsonPath('error', 'Manager role required.')
            ->assertJsonPath('message', 'Manager role required.')
            ->assertJsonPath('localized_message', __('errors.VALIDATION_FAILED'));
    }

    public function test_429_response_has_localized_message(): void
    {
        // #4955 : tout 429 (y compris abort(429)) sert désormais le code
        // stable TOO_MANY_REQUESTS + message localisé — le message brut
        // « Too Many Requests. » n'est plus exposé.
        $this->getJson('/api/_test/err-429')
            ->assertStatus(429)
            ->assertJsonPath('error', 'TOO_MANY_REQUESTS')
            ->assertJsonPath('message', 'TOO_MANY_REQUESTS')
            ->assertJsonPath('localized_message', __('errors.TOO_MANY_REQUESTS'));
    }

    public function test_localized_message_follows_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/_test/err-403')
            ->assertStatus(403)
            ->assertJsonPath('localized_message', __('errors.FORBIDDEN', [], 'en'));
    }
}
