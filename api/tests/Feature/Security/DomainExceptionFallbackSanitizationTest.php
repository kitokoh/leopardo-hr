<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * #4171 (audit 2026-08-16) : quand le code d'erreur d'une DomainException
 * n'existe pas au catalogue lang/{locale}/errors.php, le renderer ne doit
 * jamais exposer le message brut (français interne) dans `localized_message`
 * — réponse générique localisée + trace serveur uniquement.
 */
class DomainExceptionFallbackSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::get('/_test/unknown-domain-code', function (): never {
                throw new DomainException(
                    'Absence impossible : conflit avec un congé du 12/07/2026 (interne, ne doit pas fuiter).',
                    422,
                    'ABSENCE_DATE_CONFLICT_RAW'
                );
            });
            Route::get('/_test/known-domain-code', function (): never {
                throw new DomainException('INVALID_CREDENTIALS interne', 409, 'INVALID_CREDENTIALS');
            });
        });
    }

    public function test_unknown_error_code_falls_back_to_generic_localized_message(): void
    {
        $response = $this->getJson('/api/_test/unknown-domain-code');

        $response->assertStatus(422)
            ->assertJsonPath('error', 'ABSENCE_DATE_CONFLICT_RAW')
            ->assertJsonPath('message', 'ABSENCE_DATE_CONFLICT_RAW')
            ->assertJsonPath('localized_message', __('errors.SERVER_ERROR'));

        $content = (string) $response->getContent();

        // Le message brut ne doit jamais sortir.
        $this->assertStringNotContainsString('conflit avec un congé', $content);
        $this->assertStringNotContainsString('ne doit pas fuiter', $content);
    }

    public function test_known_error_code_still_returns_catalog_translation(): void
    {
        $response = $this->getJson('/api/_test/known-domain-code');

        $response->assertStatus(409)
            ->assertJsonPath('localized_message', __('errors.INVALID_CREDENTIALS'));
        $this->assertStringNotContainsString('interne', (string) $response->getContent());
    }
}
