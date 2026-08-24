<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RTMX (#5277) — IdempotencyMiddleware : rejeu sûr des écritures
 * (POST/PUT/PATCH) via le header `Idempotency-Key`.
 *
 * Couvre la spec `.specify/features/5277-rtmx/spec.md` (US2 + US3) :
 * - rejeu à l'identique d'une réponse 2xx pour une retentative (même clé,
 *   même corps, même token) ;
 * - corps différent → traitement frais (signature corps dans la clé) ;
 * - requêtes anonymes jamais dédupliquées ;
 * - échec (non-2xx) jamais mémorisé ;
 * - clé mal formée → 422 `INVALID_IDEMPOTENCY_KEY` localisé ;
 * - verrou anti-course → 409 `IDEMPOTENCY_IN_PROGRESS` ;
 * - clé de cache scopée par token (aucune fuite inter-utilisateur).
 */
class IdempotencyMiddlewareTest extends TestCase
{
    private const KEY = 'rtmx-key-12345678';

    private const AUTH = 'Bearer test-rtmx-token-001';

    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::post('/_test/rtmx-idem', fn (Request $request) => response()->json([
                'received' => $request->input('n'),
                'id' => (string) Str::uuid(),
            ]));

            $attempts = 0;
            Route::post('/_test/rtmx-fail', function () use (&$attempts): JsonResponse {
                ++$attempts;

                return response()->json(['attempt' => $attempts], 422);
            });
        });
    }

    public function test_without_key_every_request_is_processed(): void
    {
        $first = $this->postJson('/api/_test/rtmx-idem', ['n' => 1], ['Authorization' => self::AUTH]);
        $second = $this->postJson('/api/_test/rtmx-idem', ['n' => 1], ['Authorization' => self::AUTH]);

        $first->assertOk();
        $second->assertOk();
        $this->assertNotSame(
            $first->json('id'),
            $second->json('id'),
            'Sans Idempotency-Key, chaque requête doit être exécutée.'
        );
    }

    public function test_same_key_and_body_replays_stored_response(): void
    {
        $first = $this->postJson(
            '/api/_test/rtmx-idem',
            ['n' => 7],
            ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY]
        );
        $first->assertOk()->assertHeaderMissing('Idempotent-Replayed');

        $second = $this->postJson(
            '/api/_test/rtmx-idem',
            ['n' => 7],
            ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY]
        );

        $second->assertOk();
        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('id'), $second->json('id'), 'La réponse doit être rejouée à l\'identique.');
        $this->assertSame(200, $second->getStatusCode());
    }

    public function test_same_key_with_different_body_is_processed_freshly(): void
    {
        $first = $this->postJson(
            '/api/_test/rtmx-idem',
            ['n' => 1],
            ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY]
        );
        $second = $this->postJson(
            '/api/_test/rtmx-idem',
            ['n' => 2],
            ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY]
        );

        $first->assertOk();
        $second->assertOk()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(1, $first->json('received'));
        $this->assertSame(2, $second->json('received'));
        $this->assertNotSame($first->json('id'), $second->json('id'));
    }

    public function test_anonymous_requests_are_never_deduplicated(): void
    {
        $first = $this->postJson('/api/_test/rtmx-idem', ['n' => 5], ['Idempotency-Key' => self::KEY]);
        $second = $this->postJson('/api/_test/rtmx-idem', ['n' => 5], ['Idempotency-Key' => self::KEY]);

        $first->assertOk();
        $second->assertOk();
        $this->assertNotSame($first->json('id'), $second->json('id'));
    }

    public function test_failed_responses_are_not_cached(): void
    {
        $headers = ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY];

        $this->postJson('/api/_test/rtmx-fail', ['n' => 1], $headers)->assertStatus(422)->assertJsonPath('attempt', 1);
        $this->postJson('/api/_test/rtmx-fail', ['n' => 1], $headers)->assertStatus(422)->assertJsonPath('attempt', 2);
    }

    public function test_malformed_key_returns_localized_422(): void
    {
        $this->postJson('/api/_test/rtmx-idem', ['n' => 1], [
            'Authorization' => self::AUTH,
            'Idempotency-Key' => 'abc',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_IDEMPOTENCY_KEY')
            ->assertJsonPath('message', 'INVALID_IDEMPOTENCY_KEY')
            ->assertJsonPath('localized_message', __('errors.INVALID_IDEMPOTENCY_KEY'));
    }

    public function test_concurrent_identical_request_returns_409(): void
    {
        $body = '{"n":7}';
        $signature = sha1('POST|/api/_test/rtmx-idem|'.sha1($body));
        $lockKey = 'rtmx:idem:'.sha1(self::AUTH).':'.self::KEY.':'.$signature.':lock';
        Cache::add($lockKey, (string) time(), 60);

        $this->postJson('/api/_test/rtmx-idem', ['n' => 7], [
            'Authorization' => self::AUTH,
            'Idempotency-Key' => self::KEY,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'IDEMPOTENCY_IN_PROGRESS')
            ->assertJsonPath('message', 'IDEMPOTENCY_IN_PROGRESS')
            ->assertJsonPath('localized_message', __('errors.IDEMPOTENCY_IN_PROGRESS'));
    }

    public function test_cache_key_is_scoped_per_token(): void
    {
        $headersA = ['Authorization' => self::AUTH, 'Idempotency-Key' => self::KEY];
        $headersB = ['Authorization' => 'Bearer test-rtmx-token-002', 'Idempotency-Key' => self::KEY];

        $first = $this->postJson('/api/_test/rtmx-idem', ['n' => 9], $headersA);
        $second = $this->postJson('/api/_test/rtmx-idem', ['n' => 9], $headersB);

        $first->assertOk();
        $second->assertOk()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertNotSame(
            $first->json('id'),
            $second->json('id'),
            'Deux tokens différents ne doivent jamais partager la réponse idempotente.'
        );
    }
}
