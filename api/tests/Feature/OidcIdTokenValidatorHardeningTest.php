<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Infrastructure\Services\SSO\OidcIdTokenValidator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Issue #8053 — durcissement fail-closed du validateur OIDC :
 *
 * - `exp` OBLIGATOIRE (OIDC Core §2) : un id_token sans `exp` n'expirait
 *   jamais (exp=0 court-circuitait le contrôle) ;
 * - `iat` OBLIGATOIRE : le contrôle « iat dans le futur » ne vaut que si le
 *   claim existe ;
 * - contrôle d'audience JAMAIS sauté : une liste `audiences` vide (ou ne
 *   contenant que des chaînes vides), ou un `client_id` vide en fallback,
 *   est rejeté comme erreur de configuration au lieu de laisser passer un
 *   token émis pour un autre client.
 *
 * Le JWKS est semé directement dans le cache (clé `sso:oidc:jwks:{md5(uri)}`)
 * — aucune sortie HTTP n'est nécessaire.
 */
class OidcIdTokenValidatorHardeningTest extends TestCase
{
    private const ISSUER = 'https://idp.example.com';

    private const JWKS_URI = 'https://idp.example.com/jwks';

    private const KID = 'hardening-key-1';

    private OidcIdTokenValidator $validator;

    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->validator = new OidcIdTokenValidator;

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

        $this->privateKey = $res;

        Cache::put('sso:oidc:jwks:'.md5(self::JWKS_URI), [[
            'kty' => 'RSA',
            'kid' => self::KID,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ]], 3600);
    }

    public function test_valid_token_is_still_accepted(): void
    {
        $claims = $this->validator->validate($this->signToken(), $this->expected());

        $this->assertSame('user-8053', $claims['sub']);
    }

    public function test_token_without_exp_is_rejected(): void
    {
        $token = $this->signToken(without: ['exp']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exp');
        $this->validator->validate($token, $this->expected());
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->signToken(overrides: ['exp' => time() - 3600]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expiré');
        $this->validator->validate($token, $this->expected());
    }

    public function test_token_without_iat_is_rejected(): void
    {
        $token = $this->signToken(without: ['iat']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('iat');
        $this->validator->validate($token, $this->expected());
    }

    public function test_token_with_iat_in_the_future_is_rejected(): void
    {
        $token = $this->signToken(overrides: ['iat' => time() + 3600]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('futur');
        $this->validator->validate($token, $this->expected());
    }

    public function test_empty_audiences_list_is_rejected_instead_of_disabling_the_check(): void
    {
        // Avant #8053 : audiences: [] sautait ENTIÈREMENT le contrôle d'aud —
        // un token émis pour un AUTRE client (aud: other-client) passait.
        $token = $this->signToken(overrides: ['aud' => 'other-client']);

        $expected = $this->expected();
        $expected['audiences'] = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience');
        $this->validator->validate($token, $expected);
    }

    public function test_audiences_list_of_empty_strings_is_rejected(): void
    {
        $token = $this->signToken();

        $expected = $this->expected();
        $expected['audiences'] = ['', ''];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience');
        $this->validator->validate($token, $expected);
    }

    public function test_empty_client_id_fallback_is_rejected(): void
    {
        $token = $this->signToken();

        $expected = $this->expected();
        $expected['client_id'] = '';
        unset($expected['audiences']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience');
        $this->validator->validate($token, $expected);
    }

    public function test_token_for_another_client_is_rejected(): void
    {
        $token = $this->signToken(overrides: ['aud' => 'other-client']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience');
        $this->validator->validate($token, $this->expected());
    }

    /**
     * @return array{issuer: string, client_id: string, nonce: ?string, jwks_uri: string}
     */
    private function expected(): array
    {
        return [
            'issuer' => self::ISSUER,
            'client_id' => 'leopardo-client',
            'nonce' => null,
            'jwks_uri' => self::JWKS_URI,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $without  claims à omettre du payload
     */
    private function signToken(array $overrides = [], array $without = []): string
    {
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'kid' => self::KID,
            'typ' => 'JWT',
        ]));

        $claims = array_merge([
            'iss' => self::ISSUER,
            'sub' => 'user-8053',
            'aud' => 'leopardo-client',
            'exp' => time() + 3600,
            'iat' => time() - 10,
        ], $overrides);
        foreach ($without as $claim) {
            unset($claims[$claim]);
        }

        $payload = $this->base64UrlEncode((string) json_encode($claims));
        $signingInput = $header.'.'.$payload;

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
