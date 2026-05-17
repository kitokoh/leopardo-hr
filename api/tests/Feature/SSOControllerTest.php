<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SSOControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

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
}
