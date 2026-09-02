<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Auth;

use App\Core\Auth\Infrastructure\Services\PiiHmacLookupService;
use Tests\TestCase;

/**
 * Issue #5713 — PiiHmacLookupService : lookup HMAC irréversible, déterministe
 * et masquage PII pour les logs.
 */
class PiiHmacLookupServiceTest extends TestCase
{
    private PiiHmacLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PiiHmacLookupService();
    }

    public function test_hash_is_deterministic(): void
    {
        $this->assertSame(
            $this->service->hash('client@exemple.fr'),
            $this->service->hash('client@exemple.fr')
        );
    }

    public function test_hash_is_hex_sha256(): void
    {
        $digest = $this->service->hash('client@exemple.fr');

        $this->assertSame(64, strlen($digest));
        $this->assertTrue(ctype_xdigit($digest));
    }

    public function test_hash_normalizes_email_before_digest(): void
    {
        // Écriture et recherche doivent produire le même digest malgré les
        // variations de casse / espaces.
        $this->assertSame(
            $this->service->hash('Client@Exemple.FR'),
            $this->service->hash('client@exemple.fr')
        );
        $this->assertSame(
            $this->service->hash('  client@exemple.fr '),
            $this->service->hash('client@exemple.fr')
        );
    }

    public function test_hash_normalizes_phone_before_digest(): void
    {
        $this->assertSame(
            $this->service->hash('+33 6 12 34 56 78'),
            $this->service->hash('33612345678')
        );
        $this->assertSame(
            $this->service->hash('06.12.34.56.78'),
            $this->service->hash('0612345678')
        );
    }

    public function test_lookup_equals_hash(): void
    {
        $this->assertSame(
            $this->service->lookup('client@exemple.fr'),
            $this->service->hash('client@exemple.fr')
        );
    }

    public function test_two_different_values_produce_different_digests(): void
    {
        $this->assertNotSame(
            $this->service->hash('a@exemple.fr'),
            $this->service->hash('b@exemple.fr')
        );
    }

    public function test_mask_email_keeps_first_char_of_each_fragment(): void
    {
        // Masquage : premier caractère conservé, reste remplacé par des
        // étoiles de même longueur (jamais de fuite du fragment).
        $this->assertSame('j*********@e*****.fr', $this->service->mask('jean.dupont@exemple.fr'));
    }

    public function test_mask_phone_keeps_first_two_and_last_two(): void
    {
        $this->assertSame('06******78', $this->service->mask('06 12 34 56 78'));
    }

    public function test_mask_empty_string(): void
    {
        $this->assertSame('', $this->service->mask(''));
    }

    public function test_isHashed_detects_hex_digest(): void
    {
        $digest = $this->service->hash('client@exemple.fr');

        $this->assertTrue($this->service->isHashed($digest));
        $this->assertFalse($this->service->isHashed('client@exemple.fr'));
        $this->assertFalse($this->service->isHashed(substr($digest, 0, 32)));
    }

    public function test_mask_never_leaks_full_value(): void
    {
        $masked = $this->service->mask('jean.dupont@exemple.fr');

        $this->assertFalse(str_contains($masked, 'jean'));
        $this->assertFalse(str_contains($masked, 'dupont'));
        $this->assertStringNotContainsString('exemple.fr', $masked);
    }
}
