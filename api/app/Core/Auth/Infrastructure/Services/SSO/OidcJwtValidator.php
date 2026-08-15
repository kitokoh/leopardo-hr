<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Validation d'ID token OIDC (audit #1694 → QA #2231).
 *
 * Implémentation légère sans dépendance externe : décodage JWT (header +
 * claims), vérification exp/nbf/iat/iss/aud, puis signature via JWKS
 * (RS256/RS384/RS512 via openssl_verify, HS256/HS384/HS512 via client_secret).
 * La découverte OIDC (.well-known/openid-configuration) fournit jwks_uri et
 * token_endpoint, mise en cache 24 h.
 */
class OidcJwtValidator
{
    private const DISCOVERY_CACHE_TTL = 86400; // 24 h

    /**
     * @param  array{issuer: string, audience: string, client_secret?: string|null, jwks_uri?: string|null}  $expected
     * @return array<string, mixed>
     *
     * @throws RuntimeException quand le token est invalide (signature, dates, iss, aud)
     */
    public function validateIdToken(string $idToken, array $expected): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed ID token');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        $claims = json_decode($this->base64UrlDecode($payloadB64), true);

        if (! is_array($header) || ! is_array($claims)) {
            throw new RuntimeException('Invalid token encoding');
        }

        $now = time();

        if (isset($claims['exp']) && $now >= (int) $claims['exp']) {
            throw new RuntimeException('ID token expired');
        }
        if (isset($claims['nbf']) && $now < (int) $claims['nbf']) {
            throw new RuntimeException('ID token not yet valid');
        }
        if (isset($claims['iat']) && $now < (int) $claims['iat'] - 300) {
            throw new RuntimeException('ID token issued in the future');
        }

        if (($claims['iss'] ?? null) !== $expected['issuer']) {
            throw new RuntimeException('ID token issuer mismatch');
        }

        $audience = $claims['aud'] ?? [];
        $audiences = is_array($audience) ? $audience : [$audience];
        if (! in_array($expected['audience'], $audiences, true)) {
            throw new RuntimeException('ID token audience mismatch');
        }

        $this->verifySignature($header, $headerB64.'.'.$payloadB64, $signatureB64, $expected);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array{issuer: string, audience: string, client_secret?: string|null, jwks_uri?: string|null}  $expected
     */
    private function verifySignature(array $header, string $signingInput, string $signatureB64, array $expected): void
    {
        $alg = (string) ($header['alg'] ?? '');
        $signature = $this->base64UrlDecode($signatureB64);

        if (str_starts_with($alg, 'HS')) {
            $secret = (string) ($expected['client_secret'] ?? '');
            if ($secret === '') {
                throw new RuntimeException('HS algorithm requires client_secret');
            }

            $expectedSignature = hash_hmac(strtolower($alg), $signingInput, $secret, true);
            if (! hash_equals($expectedSignature, $signature)) {
                throw new RuntimeException('ID token signature invalid (HMAC)');
            }

            return;
        }

        if (str_starts_with($alg, 'RS')) {
            $jwksUri = $expected['jwks_uri'] ?? null;
            if (! is_string($jwksUri) || $jwksUri === '') {
                throw new RuntimeException('JWKS URI not available');
            }

            $key = $this->resolveJwksKey($jwksUri, (int) ($header['kid'] ?? 0));
            $pem = $this->buildRsaPublicKeyPem($key);
            if ($pem === null) {
                throw new RuntimeException('Unable to build RSA public key from JWKS');
            }

            $digest = match ($alg) {
                'RS256' => 'SHA256',
                'RS384' => 'SHA384',
                'RS512' => 'SHA512',
                default => throw new RuntimeException("Unsupported RSA algorithm: {$alg}"),
            };

            if (openssl_verify($signingInput, $signature, $pem, $digest) !== 1) {
                throw new RuntimeException('ID token signature invalid (RSA)');
            }

            return;
        }

        throw new RuntimeException("Unsupported ID token algorithm: {$alg}");
    }

    /**
     * @return array{issuer: string, jwks_uri: string, token_endpoint: string, authorization_endpoint: string}
     */
    public function discover(string $issuer): array
    {
        $cacheKey = 'oidc_discovery_'.md5($issuer);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['jwks_uri'], $cached['token_endpoint'])) {
            /** @var array{issuer: string, jwks_uri: string, token_endpoint: string, authorization_endpoint: string} $cached */
            return $cached;
        }

        $url = rtrim($issuer, '/').'/.well-known/openid-configuration';

        $response = Http::timeout(10)->acceptJson()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("OIDC discovery failed for {$issuer}");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $discovered = [
            'issuer' => (string) ($data['issuer'] ?? $issuer),
            'jwks_uri' => (string) ($data['jwks_uri'] ?? ''),
            'token_endpoint' => (string) ($data['token_endpoint'] ?? ''),
            'authorization_endpoint' => (string) ($data['authorization_endpoint'] ?? ''),
        ];

        if ($discovered['jwks_uri'] === '' || $discovered['token_endpoint'] === '') {
            throw new RuntimeException('OIDC discovery incomplete (jwks_uri/token_endpoint missing)');
        }

        Cache::put($cacheKey, $discovered, self::DISCOVERY_CACHE_TTL);

        Log::info('OIDC discovery cached', ['issuer' => $issuer]);

        return $discovered;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveJwksKey(string $jwksUri, int $kid): array
    {
        $response = Http::timeout(10)->acceptJson()->get($jwksUri);

        if (! $response->successful()) {
            throw new RuntimeException("JWKS fetch failed for {$jwksUri}");
        }

        $data = $response->json() ?? [];
        $keys = is_array($data) ? ($data['keys'] ?? []) : [];

        foreach ($keys as $key) {
            if (is_array($key) && (int) ($key['kid'] ?? 0) === $kid) {
                return $key;
            }
        }

        // Repli : si un seul kid, on l'utilise (certains IdP n'émettent pas kid).
        if (count($keys) === 1 && is_array($keys[0])) {
            return $keys[0];
        }

        throw new RuntimeException("JWKS key not found for kid {$kid}");
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function buildRsaPublicKeyPem(array $key): ?string
    {
        $n = $key['n'] ?? null;
        $e = $key['e'] ?? null;

        if (! is_string($n) || ! is_string($e)) {
            return null;
        }

        $modulus = $this->base64UrlDecode($n);
        $exponent = $this->base64UrlDecode($e);

        $rsaPublicKey = $this->buildRsaPublicKeyDer($modulus, $exponent);

        // SPKI : SEQUENCE { SEQUENCE { OID rsaEncryption, NULL }, BIT STRING }
        // — le BIT STRING commence par l'octet 0x00 (bits inutilisés).
        $bitString = "\x00".$rsaPublicKey;
        $algorithmSequence = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitStringTag = "\x03".$this->derLength($bitString).$bitString;
        $spki = "\x30".$this->derLength($algorithmSequence.$bitStringTag).$algorithmSequence.$bitStringTag;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem) === false ? null : $pem;
    }

    private function buildRsaPublicKeyDer(string $modulus, string $exponent): string
    {
        $encodeInteger = function (string $bytes): string {
            // Integer DER : bit de signe → préfixe 0x00 si le premier octet >= 0x80.
            if (ord($bytes[0]) & 0x80) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".$this->derLength($bytes).$bytes;
        };

        $encodedModulus = $encodeInteger($modulus);
        $encodedExponent = $encodeInteger($exponent);

        return "\x30".$this->derLength($encodedModulus.$encodedExponent).$encodedModulus.$encodedExponent;
    }

    private function derLength(string $data): string
    {
        $length = strlen($data);

        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
