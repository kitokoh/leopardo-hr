<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RTMX (#5277) — HttpCacheMiddleware : GET conditionnels (ETag / 304) pour
 * les réponses JSON 2xx du groupe `api`.
 *
 * Couvre la spec `.specify/features/5277-rtmx/spec.md` (US1) :
 * - réponse 200 + ETag + Cache-Control privé ;
 * - 304 Not Modified (corps vide) quand If-None-Match correspond ;
 * - jamais de 304 mensonger quand la ressource a changé ;
 * - non-GET et politiques explicites jamais touchés.
 */
class HttpCacheMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->middleware('api')->group(function (): void {
            Route::get('/_test/rtmx-static', fn () => response()->json(['ok' => true, 'value' => 42]));
            Route::get('/_test/rtmx-echo', fn (Request $request) => response()->json(['value' => $request->query('v', 'default')]));
            Route::get('/_test/rtmx-explicit', fn () => response()->json(['ok' => true])->header('Cache-Control', 'no-store'));
        });
    }

    public function test_get_json_response_carries_etag_and_private_cache_control(): void
    {
        // Symfony (ResponseHeaderBag) normalise l'ordre des directives
        // Cache-Control : on vérifie la politique sémantique (privé,
        // revalidation immédiate), pas l'ordre exact des directives.
        $this->getJson('/api/_test/rtmx-static')
            ->assertOk()
            ->assertHeader('ETag')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'max-age=0')
            ->assertHeaderContains('Cache-Control', 'must-revalidate');
    }

    public function test_conditional_get_returns_304_when_unchanged(): void
    {
        $first = $this->getJson('/api/_test/rtmx-static');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertIsString($etag);
        $this->assertNotSame('', $etag);

        $second = $this->getJson('/api/_test/rtmx-static', ['If-None-Match' => $etag]);

        $second->assertStatus(304);
        $second->assertHeader('ETag', $etag);
        $this->assertSame('', $second->getContent());
    }

    public function test_conditional_get_returns_full_body_when_resource_changed(): void
    {
        $first = $this->getJson('/api/_test/rtmx-echo?v=a');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertIsString($etag);

        $second = $this->getJson('/api/_test/rtmx-echo?v=b', ['If-None-Match' => $etag]);

        $second->assertOk()
            ->assertJsonPath('value', 'b');
    }

    public function test_star_if_none_match_returns_304(): void
    {
        $this->getJson('/api/_test/rtmx-static', ['If-None-Match' => '*'])
            ->assertStatus(304);
    }

    public function test_weak_etag_match_is_tolerated(): void
    {
        $first = $this->getJson('/api/_test/rtmx-static');
        $etag = $first->headers->get('ETag');
        $this->assertIsString($etag);

        $this->getJson('/api/_test/rtmx-static', ['If-None-Match' => 'W/'.$etag])
            ->assertStatus(304);
    }

    public function test_explicit_cache_policy_is_never_overridden(): void
    {
        // Symfony suffixe ', private' aux politiques explicites sans
        // public/private — on vérifie que la politique de l'app ('no-store')
        // est préservée et qu'aucun ETag n'est posé.
        $this->getJson('/api/_test/rtmx-explicit')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderMissing('ETag');
    }

    public function test_head_requests_are_not_cached(): void
    {
        $this->call('HEAD', '/api/_test/rtmx-static')
            ->assertOk()
            ->assertHeaderMissing('ETag');
    }
}
