<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\RetryTransientDatabaseErrors;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/**
 * Issue #7479 — `GET /auth/me` répondait 500 par intermittence en production :
 * une connexion valide était présentée comme un échec (pooler Postgres, plan
 * mis en cache après un déploiement → `SQLSTATE[0A000] cached plan must not
 * change result type`).
 *
 * Contrat verrouillé ici :
 *   1. une erreur transitoire sur une méthode **idempotente** est rejouée une
 *      fois, et l'utilisateur obtient la ressource quand le rejeu réussit ;
 *   2. si le rejeu échoue aussi, la réponse est **dégradée et documentée**
 *      (503 + `code` stable + `retryable`) — jamais un 500 nu ;
 *   3. une méthode **non idempotente** (POST) n'est **jamais** rejouée (risque de
 *      double écriture) ;
 *   4. une erreur **non transitoire** (vraie erreur applicative) n'est pas
 *      masquée par un rejeu ;
 *   5. le middleware est réellement branché sur le groupe `api` (une classe
 *      écrite mais non enregistrée ne protège rien).
 *
 * Les erreurs sont injectées via des routes de test : aucun accès base n'est
 * nécessaire (le middleware se contente de fermer la connexion avant rejeu).
 */
final class RetryTransientDatabaseErrorsTest extends TestCase
{
    private const TRANSIENT_PATH = '__test/transient-db-error';

    private const FATAL_PATH = '__test/fatal-db-error';

    private const POST_PATH = '__test/transient-db-error-post';

    protected function setUp(): void
    {
        parent::setUp();

        /** @var array<string, int> $attempts */
        $attempts = ['transient' => 0, 'fatal' => 0, 'post' => 0];
        app()->instance('__test_attempts', $attempts);

        Route::middleware([RetryTransientDatabaseErrors::class])->group(function (): void {
            Route::get(self::TRANSIENT_PATH, function (): array {
                $this->countAttempt('transient');

                // Le rejeu doit réussir : seule la première tentative échoue.
                if (app('__test_attempts')['transient'] < 2) {
                    throw self::cachedPlanException();
                }

                return ['data' => ['id' => 1]];
            });

            Route::get(self::FATAL_PATH, function (): array {
                $this->countAttempt('fatal');

                throw new QueryException(
                    'pgsql',
                    'select * from "employees" where "id" = 1',
                    [],
                    self::pdoException('SQLSTATE[42703]: Undefined column: column "nope" does not exist'),
                );
            });

            Route::post(self::POST_PATH, function (): array {
                $this->countAttempt('post');

                throw self::cachedPlanException();
            });
        });
    }

    public function test_transient_error_on_idempotent_request_is_retried_and_succeeds(): void
    {
        $response = $this->getJson('/'.self::TRANSIENT_PATH);

        $response->assertOk()->assertJsonPath('data.id', 1);
        $this->assertSame(2, app('__test_attempts')['transient'], 'la requête doit être rejouée une fois');
    }

    public function test_transient_error_persisting_returns_documented_503(): void
    {
        // Route qui échoue TOUJOURS en erreur transitoire.
        Route::middleware([RetryTransientDatabaseErrors::class])
            ->get('__test/always-transient', function (): array {
                throw self::cachedPlanException();
            });

        $response = $this->getJson('/__test/always-transient');

        $response->assertStatus(503)
            ->assertJsonPath('error', 'SERVICE_UNAVAILABLE')
            ->assertJsonPath('code', 'DB_TRANSIENT')
            ->assertJsonPath('retryable', true)
            ->assertHeader('Retry-After');
    }

    public function test_non_idempotent_request_is_never_retried(): void
    {
        $response = $this->postJson('/'.self::POST_PATH, []);

        $response->assertStatus(500);
        $this->assertSame(1, app('__test_attempts')['post'], 'un POST ne doit jamais être rejoué');
    }

    public function test_non_transient_error_is_not_masked(): void
    {
        $response = $this->getJson('/'.self::FATAL_PATH);

        $response->assertStatus(500);
        $this->assertSame(1, app('__test_attempts')['fatal'], 'une erreur applicative ne doit pas être rejouée');
    }

    public function test_middleware_is_registered_on_the_api_group(): void
    {
        /** @var Router $router */
        $router = app(Router::class);
        $groups = $router->getMiddlewareGroups();

        $this->assertContains(
            RetryTransientDatabaseErrors::class,
            $groups['api'] ?? [],
            'le middleware doit être branché sur le groupe api (sinon il ne protège aucune route)',
        );
    }

    private function countAttempt(string $key): void
    {
        $attempts = app('__test_attempts');
        $attempts[$key]++;
        app()->instance('__test_attempts', $attempts);
    }

    private static function cachedPlanException(): QueryException
    {
        return new QueryException(
            'pgsql',
            'select * from "employees" where "employees"."id" = 299 limit 1',
            [],
            self::pdoException(
                'SQLSTATE[0A000]: Feature not supported: 7 ERROR: cached plan must not change result type',
            ),
        );
    }

    /**
     * Le message porte le SQLSTATE, comme le fait le driver réel
     * (`SQLSTATE[0A000]: …`) : le middleware reconnaît l'erreur par son code
     * **ou** par son message, sans qu'on ait à muter une propriété d'exception.
     */
    private static function pdoException(string $message): PDOException
    {
        return new PDOException($message);
    }
}
