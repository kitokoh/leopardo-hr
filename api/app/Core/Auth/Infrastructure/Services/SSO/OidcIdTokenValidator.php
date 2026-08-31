<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services\SSO;

use App\Rules\NotPrivateUrl;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validation des ID tokens OpenID Connect (issue #2231 / #2197 / #2251).
 *
 * Implémentation sans dépendance externe : décodage base64url, vérification
 * de signature RS256/RS384/RS512 via openssl_verify() contre la clé JWKS de
 * l'IdP (jwks_uri de la config), et validation des claims (iss, aud, exp,
 * iat, nonce).
 *
 * Le JWKS est mis en cache 1 h (clé `sso:oidc:jwks:{md5(jwks_uri)}`) pour
 * limiter les allers-retours vers l'IdP.
 */
final class OidcIdTokenValidator
{
    private const ALLOWED_ALGS = ['RS256', 'RS384', 'RS512'];

    private const CLOCK_SKEW_SECONDS = 60;

    private const JWKS_CACHE_TTL_SECONDS = 3600;

    /**
     * @param  array{issuer: string, client_id: string, nonce: ?string, jwks_uri: string, audiences?: list<string>}  $expected
     * @return array<string, mixed> claims validés
     *
     * @throws \RuntimeException quand le token est invalide
     *         (signature, émetteur, audience, expiration ou nonce).
     *
     * Note : si `audiences` est fourni (liste de client_id acceptés), l'aud
     * du token doit intersecter cette liste. Si `audiences` est une liste
     * vide, le contrôle d'audience est sauté (mode dev — signature, iss et
     * exp restent obligatoires). Issue #3941.
     */
    public function validate(string $idToken, array $expected): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new \RuntimeException('OIDC id_token malformé.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = $this->decodeJson($headerB64);
        $claims = $this->decodeJson($payloadB64);
        $signature = $this->base64UrlDecode($signatureB64);

        $alg = (string) ($header['alg'] ?? '');

        if (! in_array($alg, self::ALLOWED_ALGS, true)) {
            throw new \RuntimeException("OIDC id_token : algorithme [{$alg}] non autorisé.");
        }

        if (! $this->verifySignature($headerB64.'.'.$payloadB64, $signature, $alg, (string) ($header['kid'] ?? ''), (string) $expected['jwks_uri'])) {
            throw new \RuntimeException('OIDC id_token : signature invalide.');
        }

        if (($claims['iss'] ?? null) !== $expected['issuer']) {
            throw new \RuntimeException('OIDC id_token : émetteur (iss) inattendu.');
        }

        $aud = $claims['aud'] ?? [];
        $auds = is_array($aud) ? array_map('strval', $aud) : [(string) $aud];
        $allowedAudiences = array_values(array_filter(array_map(
            'strval',
            (array) ($expected['audiences'] ?? [(string) $expected['client_id']])
        ), static fn (string $value): bool => $value !== ''));
        if ($allowedAudiences !== [] && array_intersect($auds, $allowedAudiences) === []) {
            throw new \RuntimeException('OIDC id_token : audience (aud) invalide.');
        }

        $exp = (int) ($claims['exp'] ?? 0);
        if ($exp > 0 && $exp < time() - self::CLOCK_SKEW_SECONDS) {
            throw new \RuntimeException('OIDC id_token : expiré.');
        }

        $iat = (int) ($claims['iat'] ?? 0);
        if ($iat > 0 && $iat > time() + self::CLOCK_SKEW_SECONDS) {
            throw new \RuntimeException('OIDC id_token : iat dans le futur.');
        }

        if (($expected['nonce'] ?? null) !== null) {
            $nonce = (string) ($claims['nonce'] ?? '');
            if ($nonce === '' || ! hash_equals((string) $expected['nonce'], $nonce)) {
                throw new \RuntimeException('OIDC id_token : nonce invalide.');
            }
        }

        return $claims;
    }

    private function verifySignature(string $signingInput, string $signature, string $alg, string $kid, string $jwksUri): bool
    {
        $pem = $this->resolveKeyPem($kid, $jwksUri);

        if ($pem === null) {
            return false;
        }

        // #6542 — mapper l'algorithme du header JWT vers la constante OpenSSL
        // (RS384/RS512 étaient rejetés car toujours vérifiés en SHA256).
        $opensslAlgo = match ($alg) {
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => OPENSSL_ALGO_SHA256,
        };

        return openssl_verify($signingInput, $signature, $pem, $opensslAlgo) === 1;
    }

    private function resolveKeyPem(string $kid, string $jwksUri): ?string
    {
        $keys = $this->jwksKeys($jwksUri);

        // #6542 — fail-closed : un kid inconnu est rejeté (plus de repli sur
        // la première clé RSA du JWKS, qui masquait une mauvaise rotation).
        foreach ($keys as $key) {
            if (($key['kid'] ?? '') === $kid) {
                return $this->keyToPem($key);
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jwksKeys(string $jwksUri): array
    {
        $cacheKey = 'sso:oidc:jwks:'.md5($jwksUri);

        $keys = Cache::get($cacheKey);

        if (is_array($keys)) {
            /** @var list<array<string, mixed>> $keys */
            return $keys;
        }

        $host = parse_url($jwksUri, PHP_URL_HOST);
        if (! is_string($host) || ! NotPrivateUrl::isPublicHost($host)) {
            Log::warning('OIDC JWKS fetch blocked by SSRF guard', ['jwks_uri' => $jwksUri]);

            return [];
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($jwksUri);
        } catch (\Throwable $e) {
            Log::warning('OIDC JWKS fetch failed', ['jwks_uri' => $jwksUri, 'error' => $e->getMessage()]);

            return [];
        }

        $keys = $this->extractKeys($response->json());

        Cache::put($cacheKey, $keys, self::JWKS_CACHE_TTL_SECONDS);

        return $keys;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractKeys(mixed $payload): array
    {
        if (! is_array($payload) || ! isset($payload['keys']) || ! is_array($payload['keys'])) {
            return [];
        }

        return array_values(array_filter(
            $payload['keys'],
            fn (mixed $key): bool => is_array($key) && (($key['kty'] ?? '') === 'RSA' || isset($key['x5c']))
        ));
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function keyToPem(array $key): ?string
    {
        // Clé au format x5c (certificat DER en base64).
        if (isset($key['x5c'][0]) && is_string($key['x5c'][0])) {
            $der = base64_decode($key['x5c'][0], true);

            if ($der === false) {
                return null;
            }

            return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
        }

        // Clé RSA JWK (n/e).
        if (($key['kty'] ?? '') !== 'RSA' || ! isset($key['n']) || ! isset($key['e'])) {
            return null;
        }

        $n = $this->base64UrlDecode((string) $key['n']);
        $e = $this->base64UrlDecode((string) $key['e']);

        if ($n === '' || $e === '') {
            return null;
        }

        $der = $this->rsaPublicKeyDer($n, $e);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----'."\n";
    }

    /**
     * Construit la structure DER SubjectPublicKeyInfo (SPKI) pour une clé
     * RSA (n, e) — même encodage que openssl_pkey_get_public() attend.
     *
     * Issue #4096 : l'ancienne implémentation produisait un DER invalide sur
     * OpenSSL 3 (« Supplied key param cannot be coerced into a public key ») :
     *  - les INTEGER n'étaient pas signés (pas de préfixe 0x00 quand le bit de
     *    poids fort est à 1) — pour une clé RSA 2048 bits, le MSB de n est
     *    TOUJOURS à 1 (2^2047 ≤ n < 2^2048) → préfixe systématiquement requis ;
     *  - les longueurs SEQUENCE étaient calculées avec des constantes magiques
     *    (+12/+4) au lieu de la somme réelle des encodages (tag + champ de
     *    longueur + contenu), tronquant la SEQUENCE dès que le champ de
     *    longueur de n passe à 3 octets.
     */
    private function rsaPublicKeyDer(string $n, string $e): string
    {
        // Séquence SPKI : SEQUENCE { SEQUENCE { OID rsaEncryption, NULL }, BIT STRING { RSAPublicKey } }
        $rsaOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        // RSAPublicKey ::= SEQUENCE { modulus INTEGER, publicExponent INTEGER }
        // (RFC 8017 A.1.1) — chaque INTEGER est encodé tag + longueur + contenu.
        $nEncoded = "\x02".$this->derLength(strlen($this->positiveInteger($n))).$this->positiveInteger($n);
        $eEncoded = "\x02".$this->derLength(strlen($this->positiveInteger($e))).$this->positiveInteger($e);

        $rsaPublicKey = "\x30".$this->derLength(strlen($nEncoded) + strlen($eEncoded)).$nEncoded.$eEncoded;

        $bitString = "\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;

        return "\x30".$this->derLength(strlen($rsaOid) + strlen($bitString)).$rsaOid.$bitString;
    }

    /**
     * Encode un entier positif en INTEGER non signé (X.690 8.3.2) : préfixe
     * 0x00 quand le bit de poids fort est à 1, sinon le contenu tel quel.
     */
    private function positiveInteger(string $bytes): string
    {
        if ($bytes === '') {
            return $bytes;
        }

        return (ord($bytes[0]) & 0x80) !== 0 ? "\x00".$bytes : $bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $segment): array
    {
        $json = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($json)) {
            throw new \RuntimeException('OIDC id_token : segment JSON illisible.');
        }

        return $json;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
