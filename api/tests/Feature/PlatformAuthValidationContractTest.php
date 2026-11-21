<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #9687 — `POST /api/v1/platform/auth/login` renvoyait **500 INTERNAL_ERROR**
 * au lieu de **422 VALIDATION_ERROR** dès que la validation échouait.
 *
 * Cause : `PlatformAuthController::login()` enveloppe `attemptLogin()` dans un
 * `catch (Throwable)` (garde #6974, traçage des 500 inexpliqués). Or
 * `Illuminate\Validation\ValidationException` hérite de `Throwable` : la
 * validation levée par `$request->validate()` était absorbée par ce catch,
 * convertie en 500, et le renderer global `ValidationException -> 422` de
 * `bootstrap/app.php` n'était jamais atteint.
 *
 * Ces tests verrouillent le contrat d'erreur de l'endpoint : toute régression
 * qui ré-absorbe la ValidationException les fait échouer avec un 500.
 */
class PlatformAuthValidationContractTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // Les compteurs de verrouillage vivent dans le cache : on part d'un
        // état propre pour que les assertions 401/423 soient déterministes.
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_missing_email_returns_422_not_500(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'password' => 'whatever-2026',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'VALIDATION_ERROR');
        $response->assertJsonPath('message', 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_empty_body_returns_422_with_both_required_fields(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_malformed_email_returns_422_not_500(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'pas-une-adresse',
            'password' => 'whatever-2026',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_overlong_device_name_returns_422_not_500(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'admin@leopardo.test',
            'password' => 'whatever-2026',
            'device_name' => str_repeat('a', 300),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'VALIDATION_ERROR');
        $response->assertJsonValidationErrors(['device_name']);
    }

    /**
     * Garde-fou : le correctif ne doit pas transformer un vrai échec
     * d'authentification en autre chose. Un mot de passe faux sur un compte
     * inexistant reste 401 INVALID_CREDENTIALS — surtout pas 422 ni 500.
     */
    public function test_invalid_credentials_still_return_401(): void
    {
        $response = $this->postJson('/api/v1/platform/auth/login', [
            'email' => 'inconnu@leopardo.test',
            'password' => 'mauvais-mot-de-passe-2026',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    /**
     * Garde-fou : les erreurs applicatives NON-HTTP doivent toujours être
     * interceptées par le `catch (Throwable)` et normalisées en 500
     * `INTERNAL_ERROR` — c'est le comportement voulu par #6974. Le rethrow
     * ciblé (#9687) ne doit pas avoir supprimé cette protection.
     *
     * On ne casse PAS le schéma ici (un `Schema::drop` laissait la table
     * absente pour les tests suivants du même worker). On vérifie la
     * propriété au niveau du code : le `catch (Throwable)` est toujours
     * présent et couvre bien les exceptions non-HTTP.
     */
    public function test_generic_throwable_catch_is_still_present(): void
    {
        $source = file_get_contents(
            app_path('Core/Auth/Interfaces/Api/V1/Controllers/PlatformAuthController.php')
        );

        $this->assertIsString($source);

        // Le rethrow ciblé existe...
        $this->assertStringContainsString(
            'catch (ValidationException|HttpExceptionInterface $exception)',
            $source,
            'Le rethrow ciblé #9687 a disparu : la ValidationException redeviendrait un 500.'
        );

        // ...et le filet de sécurité #6974 est toujours là, APRÈS le rethrow.
        $rethrowPos = strpos($source, 'catch (ValidationException|HttpExceptionInterface $exception)');
        $throwablePos = strpos($source, 'catch (Throwable $exception)');

        $this->assertNotFalse($throwablePos, 'Le catch (Throwable) de #6974 a disparu.');
        $this->assertGreaterThan(
            $rethrowPos,
            $throwablePos,
            'Le catch (Throwable) doit venir APRÈS le rethrow ciblé, sinon il ré-absorbe la validation.'
        );
    }
}
