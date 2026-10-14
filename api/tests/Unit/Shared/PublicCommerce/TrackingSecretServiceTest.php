<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\PublicCommerce;

use App\Shared\Services\PublicCommerce\TrackingSecretService;
use Tests\TestCase;

/**
 * BOS-050 (#8208) — secret de suivi public : génération 64 hex, hash
 * stocké (jamais le clair), comparaison timing-safe, fail-closed sur vide.
 * #8464 — écritures en HMAC-SHA256 keyed (clé applicative), vérification
 * DUAL-READ : les hash SHA-256 legacy restent acceptés.
 */
class TrackingSecretServiceTest extends TestCase
{
    private TrackingSecretService $secrets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secrets = new TrackingSecretService;
    }

    public function test_generate_returns_a_64_hex_plain_secret_and_its_hmac_hash(): void
    {
        $generated = $this->secrets->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $generated['plain']);
        $this->assertSame(
            hash_hmac('sha256', $generated['plain'], (string) config('app.key')),
            $generated['hash']
        );
        $this->assertNotSame($generated['plain'], $generated['hash']);
    }

    public function test_hmac_differs_from_bare_sha256(): void
    {
        $plain = 'K7P9XQ2M';

        $this->assertNotSame(hash('sha256', $plain), $this->secrets->hmac($plain));
        $this->assertSame(
            hash_hmac('sha256', $plain, (string) config('app.key')),
            $this->secrets->hmac($plain)
        );
    }

    public function test_matches_still_accepts_a_legacy_bare_sha256_hash(): void
    {
        // Lignes écrites avant #8464 (travel, restaurant, hospitality, vtc) :
        // le secret déjà diffusé doit rester vérifiable (dual-read).
        $plain = 'K7P9XQ2M';
        $legacyHash = hash('sha256', $plain);

        $this->assertTrue($this->secrets->matches($plain, $legacyHash));
    }

    public function test_matches_rejects_a_legacy_hash_of_a_different_secret(): void
    {
        $legacyHash = hash('sha256', 'AAAAAAAA');

        $this->assertFalse($this->secrets->matches('BBBBBBBB', $legacyHash));
    }

    public function test_generate_produces_unique_secrets(): void
    {
        $a = $this->secrets->generate();
        $b = $this->secrets->generate();

        $this->assertNotSame($a['plain'], $b['plain']);
        $this->assertNotSame($a['hash'], $b['hash']);
    }

    public function test_matches_accepts_the_presented_secret_against_the_stored_hash(): void
    {
        $generated = $this->secrets->generate();

        $this->assertTrue($this->secrets->matches($generated['plain'], $generated['hash']));
    }

    public function test_matches_rejects_a_wrong_secret(): void
    {
        $generated = $this->secrets->generate();

        $this->assertFalse($this->secrets->matches(str_repeat('a', 64), $generated['hash']));
    }

    public function test_matches_is_fail_closed_on_empty_or_null_stored_hash(): void
    {
        $generated = $this->secrets->generate();

        $this->assertFalse($this->secrets->matches($generated['plain'], null));
        $this->assertFalse($this->secrets->matches($generated['plain'], ''));
        $this->assertFalse($this->secrets->matches('', $generated['hash']));
    }
}
