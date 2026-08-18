<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * #4955 (audit web client 2026-08-17) — les réponses 429 doivent être
 * localisées (code stable + localized_message), jamais le message Laravel
 * brut « Too Many Attempts. ».
 */
class ThrottleResponsesLocalizedTest extends TestCase
{
    public function test_throttled_route_returns_localized_429(): void
    {
        Route::middleware('throttle:2,1')->get('/api/v1/_test-throttle-localized', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/_test-throttle-localized')->assertOk();
        $this->getJson('/api/v1/_test-throttle-localized')->assertOk();

        $response = $this->withHeader('Accept-Language', 'fr')
            ->getJson('/api/v1/_test-throttle-localized');

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'TOO_MANY_REQUESTS',
                'localized_message' => 'Trop de requêtes. Réessayez plus tard.',
            ]);
    }

    public function test_throttled_route_localizes_english(): void
    {
        Route::middleware('throttle:2,1')->get('/api/v1/_test-throttle-localized-en', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/_test-throttle-localized-en')->assertOk();
        $this->getJson('/api/v1/_test-throttle-localized-en')->assertOk();

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/_test-throttle-localized-en')
            ->assertStatus(429)
            ->assertJsonPath('localized_message', 'Too many requests. Try again later.');
    }
}
