<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth\Infrastructure\Services\SSO\OidcIdTokenValidator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Issue #4096 — la construction DER SPKI de rsaPublicKeyDer() doit produire
 * une clé publique acceptée par OpenSSL 3 (« Supplied key param cannot be
 * coerced » sur l'ancien encodage : INTEGER non signé + longueurs magiques).
 *
 * L'ancien code échouait sur OpenSSL ≥ 3 (ubuntu-latest, Render) pour toute
 * clé RSA 2048 bits (MSB de n TOUJOURS à 1) → SSO OIDC (#3941) inopérant.
 */
final class OidcIdTokenValidatorDerTest extends TestCase
{
    private function buildPublicKeyPem(string $n, string $e): string
    {
        $validator = new OidcIdTokenValidator;

        $method = new ReflectionMethod($validator, 'rsaPublicKeyDer');

        $der = $method->invoke($validator, $n, $e);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----'."\n";
    }

    public function test_openssl_accepts_the_der_built_from_a_2048_bits_key(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key, 'Impossible de générer la clé RSA de test.');

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);

        // Le MSB du modulus d'une clé 2048 bits est TOUJOURS à 1 — c'est le
        // cas qui exigeait le préfixe 0x00 (X.690 8.3.2).
        // MSB set = 0x80 = 128 (jamais 1) — issue #4159.
        $this->assertSame(128, ord($details['rsa']['n'][0]) & 0x80);

        $pem = $this->buildPublicKeyPem($details['rsa']['n'], $details['rsa']['e']);

        $public = openssl_pkey_get_public($pem);
        $this->assertNotFalse($public, 'openssl_pkey_get_public rejette le PEM construit (DER invalide).');

        $rebuilt = openssl_pkey_get_details($public);
        $this->assertIsArray($rebuilt);
        $this->assertSame($details['rsa']['n'], $rebuilt['rsa']['n'], 'Modulus divergent après reconstruction.');
        $this->assertSame($details['rsa']['e'], $rebuilt['rsa']['e'], 'Exposant divergent après reconstruction.');
    }

    public function test_openssl_verify_succeeds_with_the_rebuilt_public_key(): void
    {
        /** @var \OpenSSLAsymmetricKey $key */
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);

        $pem = $this->buildPublicKeyPem($details['rsa']['n'], $details['rsa']['e']);
        $public = openssl_pkey_get_public($pem);
        $this->assertNotFalse($public);

        $data = 'oidc-challenge-'.bin2hex(random_bytes(8));
        // PHP 8.4 : openssl_sign() retourne bool (true) au lieu de int (1)
        // (issue #4159) — openssl_verify() retourne encore int (1) sur 8.4.
        $this->assertTrue((bool) openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256));
        $this->assertSame(1, openssl_verify($data, $signature, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_der_handles_an_exponent_with_high_bit_set(): void
    {
        // Exposant 0x81 (MSB à 1 sur un octet) : exige le préfixe 0x00.
        $pem = $this->buildPublicKeyPem(str_repeat("\x80\x01", 128), "\x81");

        $public = openssl_pkey_get_public($pem);
        $this->assertNotFalse($public, 'openssl_pkey_get_public rejette un exposant MSB=1 non signé.');
    }
}
