<?php

namespace Tests\Feature\EdgeSync;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4687 — les credentials Edge (license_key, metadata, signed_payload) ne
 * doivent jamais fuiter dans les sérialisations list/show ; ils ne sont
 * exposés qu'aux moments légitimes d'émission (store / issueLicense).
 */
class EdgeCredentialVisibilityTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private EdgeNode $node;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'visibility-test-co',
            'status' => 'active',
        ]);
        $this->company = $company;

        $this->node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Principal',
            'slug' => 'site-principal-visibility',
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => [],
            'license_expires_at' => now()->addDays(30),
            'license_key' => 'lk-visibility-secret',
            'metadata' => ['edge_token' => hash('sha256', 'visibility-token')],
        ]);
    }

    /** @test */
    public function it_hides_license_key_and_metadata_in_node_list(): void
    {
        $this->actingAsCompanyAdmin($this->company);

        $response = $this->getJson('/api/v1/edge');

        $response->assertOk();
        $node = $response->json('data.0');
        $this->assertNotNull($node);
        $this->assertArrayNotHasKey('license_key', $node, 'license_key ne doit pas être sérialisé dans la liste.');
        $this->assertArrayNotHasKey('metadata', $node, 'metadata (hash edge_token) ne doit pas être sérialisé dans la liste.');
        $this->assertArrayHasKey('id', $node);
        $this->assertArrayHasKey('name', $node);
    }

    /** @test */
    public function it_hides_license_key_and_metadata_in_node_show(): void
    {
        $this->actingAsCompanyAdmin($this->company);

        $response = $this->getJson("/api/v1/edge/{$this->node->id}");

        $response->assertOk();
        $this->assertArrayNotHasKey('license_key', $response->json('data'), 'license_key ne doit pas être sérialisé dans show.');
        $this->assertArrayNotHasKey('metadata', $response->json('data'), 'metadata ne doit pas être sérialisé dans show.');
        $this->assertSame('Site Principal', $response->json('data.name'));
    }

    /** @test */
    public function it_still_exposes_license_at_registration(): void
    {
        $this->actingAsCompanyAdmin($this->company);

        $response = $this->postJson('/api/v1/edge', [
            'name' => 'Entrepôt Nord',
            'mode' => 'hybrid',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name'],
                'license' => ['license_key', 'expires_at', 'signed_payload'],
                'edge_token',
            ]);
        // Le token en clair n'est montré qu'à l'enregistrement — jamais le hash dans metadata.
        $this->assertArrayNotHasKey('metadata', $response->json('data'), 'metadata ne doit pas fuiter même à l\'enregistrement.');
        $this->assertNotEmpty($response->json('license.license_key'));
        $this->assertNotEmpty($response->json('license.signed_payload'));
    }

    /** @test */
    public function it_still_exposes_license_when_issuing(): void
    {
        $this->actingAsCompanyAdmin($this->company);

        $response = $this->postJson("/api/v1/edge/{$this->node->id}/license", [
            'valid_days' => 30,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['license_key', 'expires_at', 'signed_payload']]);
        $this->assertNotEmpty($response->json('data.license_key'));
        $this->assertNotEmpty($response->json('data.signed_payload'));
    }

    private function actingAsCompanyAdmin(Company $company): self
    {
        /** @var Employee $admin */
        $admin = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'admin',
        ]);

        return $this->actingAs($admin, 'sanctum');
    }
}
