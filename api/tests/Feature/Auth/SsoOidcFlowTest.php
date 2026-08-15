<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\SSO\SSOService;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA #2231 — flux SSO OIDC complet :
 *   GET /sso/oidc/{companyId}/authorize (redirection IdP, state en cache)
 *   GET /sso/oidc/{companyId}/callback (échange code, validation ID token
 *   JWKS iss/aud/exp, login employé, token Sanctum).
 */
class SsoOidcFlowTest extends TestCase
{
    use CreatesMvpSchema;

    private const ISSUER = 'https://idp.example.com';

    private const CLIENT_ID = 'leopardo-test-client';

    private Company $company;

    private Employee $employee;

    private string $privateKeyPem;

    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'sso.user@example.com',
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employee = $employee;

        // Paire RSA de test + JWKS.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $exported = '';
        openssl_pkey_export($key, $exported);
        $this->privateKeyPem = $exported;
        $details = openssl_pkey_get_details($key);
        $this->jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 1,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $this->b64url($details['rsa']['n']),
                'e' => $this->b64url($details['rsa']['e']),
            ]],
        ];

        // Config SSO OIDC complète → active.
        $this->app->make(SSOService::class)->configureSSO((string) $company->id, 'oidc', [
            'entity_id' => self::ISSUER,
            'sso_url' => self::ISSUER.'/authorize',
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'test-client-secret',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function fakeOidcEndpoints(?string $idToken = null): void
    {
        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'jwks_uri' => self::ISSUER.'/jwks',
                'token_endpoint' => self::ISSUER.'/token',
                'authorization_endpoint' => self::ISSUER.'/authorize',
            ]),
            self::ISSUER.'/jwks' => Http::response($this->jwks),
            self::ISSUER.'/token' => Http::response(
                $idToken !== null
                    ? ['id_token' => $idToken, 'access_token' => 'at-test', 'token_type' => 'Bearer']
                    : ['error' => 'invalid_grant'],
                200
            ),
        ]);
    }

    private function buildIdToken(array $overrides = []): string
    {
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 1], JSON_THROW_ON_ERROR));
        $claims = $this->b64url(json_encode([
            'iss' => self::ISSUER,
            'sub' => 'user-123',
            'aud' => self::CLIENT_ID,
            'exp' => time() + 3600,
            'iat' => time(),
            'email' => $this->employee->email,
            ...$overrides,
        ], JSON_THROW_ON_ERROR));

        $signingInput = $header.'.'.$claims;
        openssl_sign($signingInput, $signature, $this->privateKeyPem, 'sha256WithRSAEncryption');

        return $signingInput.'.'.$this->b64url($signature);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function test_authorize_redirects_to_idp_with_state_and_nonce(): void
    {
        $this->fakeOidcEndpoints();

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize');

        $response->assertStatus(302);
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringStartsWith(self::ISSUER.'/authorize?', $location);
        $this->assertStringContainsString('client_id='.urlencode(self::CLIENT_ID), $location);
        $this->assertStringContainsString('response_type=code', $location);
        $this->assertStringContainsString('state=', $location);
        $this->assertStringContainsString('nonce=', $location);
    }

    public function test_callback_full_flow_returns_sanctum_token(): void
    {
        $idToken = $this->buildIdToken();
        $this->fakeOidcEndpoints($idToken);

        // State attendu (celui émis par authorize, mis en cache).
        Cache::put('oidc_state_'.$this->company->id, 'test-state-abc', 600);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state=test-state-abc');

        $response->assertOk();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('sso', 'oidc');
        $this->assertNotEmpty($response->json('token'));
        $response->assertJsonPath('data.email', $this->employee->email);
    }

    public function test_callback_rejects_wrong_state(): void
    {
        $idToken = $this->buildIdToken();
        $this->fakeOidcEndpoints($idToken);

        Cache::put('oidc_state_'.$this->company->id, 'expected-state', 600);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state=wrong-state');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'OIDC state mismatch');
    }

    public function test_callback_rejects_tampered_id_token(): void
    {
        $idToken = $this->buildIdToken();
        $parts = explode('.', $idToken);
        $parts[2] = $this->b64url(str_repeat('aa', 100)); // signature invalide
        $this->fakeOidcEndpoints(implode('.', $parts));

        Cache::put('oidc_state_'.$this->company->id, 'test-state-abc', 600);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state=test-state-abc');

        $response->assertStatus(422);
        $this->assertStringContainsString('signature', (string) $response->json('error'));
    }

    public function test_callback_rejects_expired_token(): void
    {
        $idToken = $this->buildIdToken(['exp' => time() - 60]);
        $this->fakeOidcEndpoints($idToken);

        Cache::put('oidc_state_'.$this->company->id, 'test-state-abc', 600);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state=test-state-abc');

        $response->assertStatus(422);
        $this->assertStringContainsString('expired', (string) $response->json('error'));
    }

    public function test_callback_404_when_email_matches_no_employee(): void
    {
        $idToken = $this->buildIdToken(['email' => 'nobody@example.com']);
        $this->fakeOidcEndpoints($idToken);

        Cache::put('oidc_state_'.$this->company->id, 'test-state-abc', 600);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state=test-state-abc');

        $response->assertStatus(404);
    }

    public function test_oidc_config_is_activated_when_complete(): void
    {
        $sso = $this->app->make(SSOService::class)->getCompanySSO((string) $this->company->id);

        $this->assertTrue($sso['enabled']);
        $this->assertSame('oidc', $sso['provider']);
    }
}
