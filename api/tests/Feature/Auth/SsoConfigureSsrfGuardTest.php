<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3318 — SSRF via config SSO OIDC/SAML :
 * les URLs d'endpoints IdP ne doivent pas pointer vers des IP privées/locales
 * (169.254.169.254, loopback, RFC1918…), et la configuration reste réservée
 * au manager principal.
 */
class SsoConfigureSsrfGuardTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public static function privateUrlPayloads(): iterable
    {
        yield 'metadata cloud AWS' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'token_url' => 'https://169.254.169.254/latest/meta-data/iam/security-credentials/'],
        ];
        yield 'loopback IPv4' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'jwks_uri' => 'https://127.0.0.1:8443/jwks'],
        ];
        yield 'RFC1918 10/8' => [
            ['provider' => 'saml', 'sso_url' => 'https://10.0.0.5/sso'],
        ];
        yield 'RFC1918 192.168/16' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'authorize_url' => 'https://192.168.1.10/authorize'],
        ];
        yield 'localhost' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'token_url' => 'http://localhost:8080/token'],
        ];
        yield 'hostname .local' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'jwks_uri' => 'https://keycloak.local/jwks'],
        ];
        yield 'hostname .internal' => [
            ['provider' => 'saml', 'slo_url' => 'https://idp.internal/slo'],
        ];
        yield 'scheme http' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'redirect_uri' => 'http://app.example.com/callback'],
        ];
        yield 'IPv6 loopback' => [
            ['provider' => 'oidc', 'issuer' => 'https://www.example.com', 'token_url' => 'https://[::1]:8443/token'],
        ];
    }

    /**
     * @dataProvider privateUrlPayloads
     */
    public function test_configure_rejects_private_or_local_endpoints(array $payload): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', $payload);

        $response->assertStatus(422);
    }

    public function test_configure_accepts_public_https_endpoints(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'oidc',
                'issuer' => 'https://www.example.com',
                'authorize_url' => 'https://www.example.com/authorize',
                'token_url' => 'https://www.example.com/token',
                'jwks_uri' => 'https://www.example.com/jwks',
                'client_id' => 'client-1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'oidc');
    }

    public function test_configure_by_plain_employee_is_forbidden(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'saml',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
            ]);

        $response->assertForbidden();
    }
}
