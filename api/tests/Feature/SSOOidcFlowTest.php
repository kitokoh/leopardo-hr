<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #2231/#2197/#2251 — flux OIDC complet : authorize (state+nonce),
 * callback avec échange de code, validation de l'id_token (signature JWKS,
 * iss/aud/exp/nonce) et émission d'un token Sanctum pour l'employé.
 */
class SSOOidcFlowTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private string $issuer = 'https://www.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Cache::flush();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function configureOidc(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/sso/configure', [
            'provider' => 'oidc',
            'client_id' => 'leopardo-client',
            'client_secret' => 's3cret',
            'issuer' => $this->issuer,
            'authorize_url' => $this->issuer.'/authorize',
            'token_url' => $this->issuer.'/token',
            'jwks_uri' => $this->issuer.'/jwks',
            'redirect_uri' => 'https://app.leopardo.test/sso/oidc/'.$this->company->id.'/callback',
            'scopes' => 'openid email profile',
        ])->assertOk()->assertJsonPath('data.provider', 'oidc');
    }

    public function test_configure_oidc_activates_config_when_complete(): void
    {
        $this->configureOidc();

        $row = DB::table('company_sso_configs')->where('company_id', $this->company->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_active, 'OIDC config must be active when the flow is complete (issue #2231).');
    }

    public function test_authorize_returns_idp_url_with_state_and_nonce(): void
    {
        $this->configureOidc();

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize');

        $response->assertOk();

        $url = $response->json('data.authorize_url');
        $this->assertIsString($url);
        $this->assertStringStartsWith($this->issuer.'/authorize', $url);

        $authUrlQuery = parse_url($url, PHP_URL_QUERY);
        parse_str(is_string($authUrlQuery) ? $authUrlQuery : '', $query);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('leopardo-client', $query['client_id'] ?? null);
        $this->assertNotEmpty($query['state'] ?? '');
        $this->assertNotEmpty($query['nonce'] ?? '');
        $this->assertSame('openid email profile', $query['scope'] ?? null);
    }

    public function test_full_oidc_flow_exchanges_code_and_issues_token(): void
    {
        $this->configureOidc();

        [$privateKey, $jwks] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        // Consomme le state de l'authorize.
        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/token' => Http::response(['id_token' => $this->signIdToken($privateKey, $nonce), 'access_token' => 'at-123'], 200),
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=auth-code-123&state='.$state);

        $response->assertOk()
            ->assertJsonPath('message', 'Connexion OIDC réussie.');

        $data = $response->json('data');
        $this->assertNotEmpty($data['token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame('sso.employee@example.com', $data['employee']['email']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/token'));
    }

    public function test_callback_accepts_direct_id_token(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce);

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertOk()
            ->assertJsonPath('data.employee.email', 'sso.employee@example.com');
    }

    public function test_callback_rejects_unknown_state(): void
    {
        $this->configureOidc();
        [$privateKey] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        $response = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?code=abc&state=forged-state');

        $response->assertStatus(422);
        // #3810 : l'API expose un code STABLE (OIDC_CALLBACK_FAILED) au lieu du
        // message d'exception brut — le détail (état inconnu/expiré/consommé)
        // reste en logs pour ne pas fuiter d'information sur un endpoint public.
        $this->assertSame('OIDC_CALLBACK_FAILED', $response->json('error'));
    }

    public function test_callback_rejects_bad_signature(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();
        [, $otherJwks] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        // JWKS servi = AUTRE clé que celle qui signe le token.
        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$otherJwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce);

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertStatus(422);
    }

    public function test_callback_rejects_wrong_issuer(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce, issuer: 'https://evil.example.com');

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertStatus(422);
    }

    public function test_callback_rejects_expired_token(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();
        $this->seedEmployeeForSso();

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce, exp: time() - 3600);

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertStatus(422);
    }

    public function test_callback_rejects_unknown_employee(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();
        // Aucun employé SSO seedé.

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce, email: 'ghost@example.com');

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertStatus(422)
            // #3810 : code stable — le détail SSO_USER_NOT_FOUND reste en logs
            // (ne pas exposer l'existence d'un email sur un endpoint public).
            ->assertJsonPath('error', 'OIDC_CALLBACK_FAILED');
    }

    public function test_callback_rejects_employee_of_another_company(): void
    {
        $this->configureOidc();
        [$privateKey, $jwks] = $this->rsaKeyPair();

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'email' => 'sso.employee@example.com',
            'status' => 'active',
        ]);
        $this->syncLookup($otherEmployee, $otherCompany);

        $authorize = $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')->assertOk()->json('data.authorize_url');
        $authorizeQuery = parse_url($authorize, PHP_URL_QUERY);
        parse_str(is_string($authorizeQuery) ? $authorizeQuery : '', $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $nonce = is_string($query['nonce'] ?? null) ? $query['nonce'] : '';

        Http::fake([
            $this->issuer.'/jwks*' => Http::response(['keys' => [$jwks]], 200),
        ]);

        $idToken = $this->signIdToken($privateKey, $nonce);

        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/callback?state='.$state.'&id_token='.urlencode($idToken))
            ->assertStatus(422)
            // #3810 : code stable — le détail SSO_TENANT_MISMATCH reste en logs.
            ->assertJsonPath('error', 'OIDC_CALLBACK_FAILED');
    }

    public function test_oidc_not_configured_returns_422(): void
    {
        $this->getJson('/api/v1/sso/oidc/'.$this->company->id.'/authorize')
            ->assertStatus(422);
    }

    private function seedEmployeeForSso(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'sso.employee@example.com',
            'status' => 'active',
        ]);

        $this->syncLookup($employee, $this->company);

        return $employee;
    }

    private function syncLookup(Employee $employee, Company $company): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('public.user_lookups')->updateOrInsert(
            ['email' => $employee->email],
            [
                'company_id' => $company->id,
                'schema_name' => 'shared_tenants',
                'employee_id' => $employee->id,
                'role' => (string) ($employee->role ?? 'employee'),
            ]
        );
    }

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: array<string, mixed>}
     */
    private function rsaKeyPair(): array
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

        $jwks = [
            'kty' => 'RSA',
            'kid' => 'test-key-1',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];

        return [$res, $jwks];
    }

    private function signIdToken(\OpenSSLAsymmetricKey $privateKey, string $nonce, ?int $exp = null, ?string $issuer = null, ?string $email = null): string
    {
        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'kid' => 'test-key-1',
            'typ' => 'JWT',
        ]));

        $claims = [
            'iss' => $issuer ?? $this->issuer,
            'sub' => 'user-123',
            'aud' => 'leopardo-client',
            'exp' => $exp ?? time() + 3600,
            'iat' => time() - 10,
            'nonce' => $nonce,
            'email' => $email ?? 'sso.employee@example.com',
            'email_verified' => true,
        ];

        $payload = $this->base64UrlEncode((string) json_encode($claims));
        $signingInput = $header.'.'.$payload;

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
