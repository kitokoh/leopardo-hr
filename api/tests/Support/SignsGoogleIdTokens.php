<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Issue #3941 — helpers pour signer de vrais ID tokens Google (RS256) et
 * faker le JWKS `https://www.googleapis.com/oauth2/v3/certs` dans les tests,
 * afin d'exercer la vérification complète (signature, iss, aud, exp,
 * email_verified) sans réseau.
 */
trait SignsGoogleIdTokens
{
    private const GOOGLE_JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';

    private const GOOGLE_TEST_KID = 'google-test-key-1';

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: array<string, mixed>}
     */
    private function googleKeyPair(): array
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($res === false) {
            $this->fail('openssl_pkey_new failed');
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            $this->fail('openssl_pkey_get_details failed');
        }

        return [$res, [
            'kty' => 'RSA',
            'kid' => self::GOOGLE_TEST_KID,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ]];
    }

    /**
     * Signe un ID token Google valide (iss accounts.google.com, email vérifié).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function googleIdToken(\OpenSSLAsymmetricKey $privateKey, array $overrides = []): string
    {
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'kid' => self::GOOGLE_TEST_KID,
            'typ' => 'JWT',
        ]));

        $claims = array_merge([
            'iss' => 'https://accounts.google.com',
            'sub' => 'google-sub-123',
            'aud' => 'leopardo-mobile-client',
            'exp' => time() + 3600,
            'iat' => time() - 10,
            'email' => 'verified.user@example.com',
            'email_verified' => true,
            'name' => 'Jane Doe',
            'picture' => 'https://example.com/avatar.png',
        ], $overrides);

        $payload = $this->base64UrlEncode((string) json_encode($claims));
        $signingInput = $header.'.'.$payload;

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * Fake le JWKS Google pour que la vérification de signature passe.
     *
     * @param  list<array<string, mixed>>  $keys
     */
    private function fakeGoogleJwks(array $keys): void
    {
        Cache::flush();
        Http::fake([
            self::GOOGLE_JWKS_URI.'*' => Http::response(['keys' => $keys], 200),
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
