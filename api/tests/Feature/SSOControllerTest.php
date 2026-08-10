<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SSOControllerTest extends TestCase
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

    public function test_providers_list_is_public(): void
    {
        $response = $this->getJson('/api/v1/sso/providers');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['provider', 'name', 'description', 'protocols'],
                ],
            ]);

        $providers = $response->json('data');
        $this->assertCount(2, $providers);
        $this->assertEquals('saml', $providers[0]['provider']);
        $this->assertEquals('oidc', $providers[1]['provider']);
    }

    public function test_sso_status_requires_principal_manager(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/sso/status');

        $response->assertForbidden();
    }

    public function test_sso_status_accessible_by_principal(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/sso/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['enabled', 'provider'],
            ]);

        $this->assertFalse($response->json('data.enabled'));
    }

    public function test_configure_sso_requires_principal_manager(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'saml',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
            ]);

        $response->assertForbidden();
    }

    public function test_configure_saml_sso(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'saml',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
                'slo_url' => 'https://idp.example.com/slo',
                'certificate' => 'MIIC...base64cert...==',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'saml')
            ->assertJsonPath('data.entity_id', 'https://idp.example.com/metadata');
    }

    public function test_configure_sso_validates_provider(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'invalid',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
            ]);

        $response->assertUnprocessable();
    }

    public function test_disable_sso(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->deleteJson('/api/v1/sso/disable');

        $response->assertOk()
            ->assertJsonPath('message', 'SSO desactive.');
    }

    public function test_saml_callback_requires_saml_response(): void
    {
        $response = $this->postJson("/api/v1/sso/saml/{$this->company->id}/callback", []);

        $response->assertStatus(400);
    }
    // ── Audit #1694 ────────────────────────────────────────────────────────

    public function test_configure_sso_encrypts_sensitive_fields_at_rest(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'oidc',
                'entity_id' => 'https://idp.example.com',
                'sso_url' => 'https://idp.example.com/oauth2/authorize',
                'client_id' => 'client-123',
                'client_secret' => 'super-secret-value',
            ])
            ->assertOk();

        $row = \Illuminate\Support\Facades\DB::table('company_sso_configs')
            ->where('company_id', $this->company->id)
            ->first();

        $this->assertNotNull($row);
        $config = json_decode((string) $row->config, true);

        // Le secret ne doit jamais apparaître en clair au repos.
        $this->assertIsString($config['client_secret']);
        $this->assertStringNotContainsString('super-secret-value', (string) $row->config);
        $this->assertStringContainsString('eyJpdiI6', (string) $row->config, 'la valeur doit être chiffrée (format Laravel Crypt)');

        // La réponse configure ne doit pas renvoyer le secret.
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'oidc',
                'entity_id' => 'https://idp.example.com',
                'sso_url' => 'https://idp.example.com/oauth2/authorize',
                'client_id' => 'client-123',
                'client_secret' => 'another-secret',
            ])
            ->assertOk();

        $response->assertJsonMissing(['client_secret' => 'another-secret']);
        $this->assertArrayNotHasKey('client_secret', $response->json('data'));
    }

    public function test_configured_sso_remains_inactive_until_validation_implemented(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/sso/configure', [
                'provider' => 'saml',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
                'certificate' => 'MIIC...base64cert...==',
            ])
            ->assertOk();

        // Audit #1694 : jamais marqué actif tant que la validation n'existe pas.
        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/v1/sso/status')
            ->assertOk();

        $this->assertFalse($response->json('data.enabled'));
    }

    public function test_saml_callback_returns_501_until_validation_is_implemented(): void
    {
        \Illuminate\Support\Facades\DB::table('company_sso_configs')->insert([
            'company_id' => $this->company->id,
            'provider' => 'saml',
            'config' => json_encode([
                'provider' => 'saml',
                'entity_id' => 'https://idp.example.com/metadata',
                'sso_url' => 'https://idp.example.com/sso',
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/sso/saml/{$this->company->id}/callback", [
            'SAMLResponse' => base64_encode('<samlp:Response>fake</samlp:Response>'),
        ]);

        // Ne jamais renvoyer un succès vide : 501 explicite.
        $response->assertStatus(501);
    }
}
