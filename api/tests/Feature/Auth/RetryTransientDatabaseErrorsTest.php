<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\RetryTransientDatabaseErrors;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
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
 *   3. une méthode **non idempotente** (POST) n'est rejouée que si le SQLSTATE
 *      **prouve que la requête n'a pas été appliquée** (#8056) : `0A000`
 *      (plan invalide) et les codes de connexion morte sont rejouables ;
 *      `25P02` (transaction déjà avortée) ne l'est **pas** — des effets de
 *      bord applicatifs ont pu être déclenchés — et donne un 503 explicite ;
 *   4. une erreur **non transitoire** (vraie erreur applicative) n'est pas
 *      masquée par un rejeu ;
 *   5. le middleware est réellement branché sur le groupe `api` (une classe
 *      écrite mais non enregistrée ne protège rien).
 *
 * Le middleware est invoqué **directement** (`handle()` + closure) plutôt que
 * via une route de test : le contrat testé est celui du middleware, et
 * l'invocation directe est déterministe (aucune dépendance à l'ordre
 * d'enregistrement des routes ni au cache de routes de l'application).
 */
final class RetryTransientDatabaseErrorsTest extends TestCase
{
    public function test_transient_error_on_idempotent_request_is_retried_and_succeeds(): void
    {
        $attempts = 0;

        $response = $this->invoke('GET', function () use (&$attempts): Response {
            $attempts++;

            // Le rejeu doit réussir : seule la première tentative échoue.
            if ($attempts < 2) {
                throw self::cachedPlanException();
            }

            return new JsonResponse(['data' => ['id' => 1]]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts, 'la requête doit être rejouée une fois');
    }

    public function test_transient_error_persisting_returns_documented_503(): void
    {
        $attempts = 0;

        $response = $this->invoke('GET', function () use (&$attempts): Response {
            $attempts++;

            throw self::cachedPlanException();
        });

        $this->assertSame(503, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('SERVICE_UNAVAILABLE', $payload['error']);
        $this->assertSame('DB_TRANSIENT', $payload['code']);
        $this->assertTrue($payload['retryable']);
        $this->assertSame('2', $response->headers->get('Retry-After'));
        $this->assertSame(2, $attempts, 'le rejeu doit avoir eu lieu avant le 503');
    }

    public function test_write_with_write_safe_sqlstate_is_retried_and_succeeds(): void
    {
        // #8056 : les écritures touchées par le pooler faisaient des 500
        // systématiques. Un 0A000 (plan invalide) n'a rien appliqué : le rejeu
        // est sûr et doit aboutir.
        $attempts = 0;

        $response = $this->invoke('POST', function () use (&$attempts): Response {
            $attempts++;

            if ($attempts < 2) {
                throw self::cachedPlanException();
            }

            return new JsonResponse(['data' => ['created' => true]]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $attempts, 'un POST sur 0A000 doit être rejoué une fois');
    }

    public function test_write_on_aborted_transaction_is_not_retried_and_returns_503(): void
    {
        // 25P02 : la transaction a déjà exécuté des instructions avant
        // d'avorter — rejouer la requête HTTP entière pourrait rejouer des
        // effets de bord (e-mail, appel externe). 503 explicite, jamais de rejeu.
        $attempts = 0;

        $response = $this->invoke('POST', function () use (&$attempts): Response {
            $attempts++;

            throw self::abortedTransactionException();
        });

        $this->assertSame(503, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('DB_TRANSIENT', $payload['code']);
        $this->assertTrue($payload['retryable']);
        $this->assertSame(1, $attempts, 'un POST sur 25P02 ne doit pas être rejoué');
    }

    public function test_non_transient_error_is_not_masked(): void
    {
        $attempts = 0;

        try {
            $this->invoke('GET', function () use (&$attempts): Response {
                $attempts++;

                throw new QueryException(
                    'pgsql',
                    'select * from "employees" where "id" = 1',
                    [],
                    self::pdoException('SQLSTATE[42703]: Undefined column: column "nope" does not exist'),
                );
            });

            $this->fail('une erreur applicative doit être propagée, pas masquée');
        } catch (QueryException $e) {
            $this->assertStringContainsString('42703', $e->getMessage());
        }

        $this->assertSame(1, $attempts, 'une erreur applicative ne doit pas être rejouée');
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

    /**
     * Invoque le middleware directement, comme le ferait le pipeline HTTP.
     */
    private function invoke(string $method, callable $handler): Response
    {
        $middleware = new RetryTransientDatabaseErrors;
        $request = Request::create('/__test/retry-transient', $method);

        return $middleware->handle($request, fn (): Response => $handler());
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

    private static function abortedTransactionException(): QueryException
    {
        return new QueryException(
            'pgsql',
            'insert into "employees" ("name") values (?)',
            ['x'],
            self::pdoException(
                'SQLSTATE[25P02]: In failed sql transaction: 7 ERROR: current transaction is aborted, commands ignored until end of transaction block',
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
