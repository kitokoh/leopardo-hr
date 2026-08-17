<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #4687 — Sérialisation Edge :
 * license_key, signed_payload et metadata (hash edge_token) ne doivent
 * JAMAIS fuiter via les endpoints de listing/détail. Le flux d'enrôlement
 * (store) et le renouvellement (issueLicense) restent les seuls à exposer
 * la licence offline au nœud.
 */
class EdgeSensitiveFieldsHiddenTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private EdgeNode $node;

    private EdgeLicense $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

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

        $this->node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Test',
            'slug' => 'site-test-'.substr((string) str()->uuid(), 0, 8),
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => ['features' => ['attendance'], 'max_employees' => 50],
            'license_key' => 'edge-license-key-secret',
            'metadata' => ['edge_token' => hash('sha256', 'edge-token-secret')],
        ]);

        $this->license = EdgeLicense::create([
            'company_id' => $this->company->id,
            'edge_node_id' => $this->node->id,
            'license_key' => 'license-key-secret',
            'signed_payload' => 'eyJhbGciOiJIUzI1NiJ9.payload.signature',
            'allowed_features' => ['attendance'],
            'max_employees' => 50,
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'last_validated_at' => now(),
            'validation_status' => 'valid',
        ]);
    }

    public function test_index_does_not_expose_license_key_or_metadata(): void
    {
        $this->actingAs($this->manager)
            ->getJson('/api/v1/edge')
            ->assertOk()
            ->assertJsonMissingPath('data.0.license_key')
            ->assertJsonMissingPath('data.0.metadata');
    }

    public function test_show_does_not_expose_license_key_or_metadata(): void
    {
        $this->actingAs($this->manager)
            ->getJson("/api/v1/edge/{$this->node->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.license_key')
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_list_all_nodes_does_not_expose_license_key_or_metadata(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Edge',
            'email' => 'sa-edge-'.uniqid().'@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();
        $superAdmin->forceFill(['status' => 'active'])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/edge/nodes')
            ->assertOk()
            ->assertJsonMissingPath('data.0.license_key')
            ->assertJsonMissingPath('data.0.metadata');
    }

    public function test_enrollment_store_exposes_license_to_node_but_never_node_secrets(): void
    {
        // POST /api/v1/edge (store) — le nœud reçoit sa licence offline.
        $response = $this->actingAs($this->manager)
            ->postJson('/api/v1/edge', [
                'name' => 'Node Neuf',
                'site_address' => 'Dakar',
                'mode' => 'hybrid',
            ])
            ->assertCreated();

        // La licence (signed_payload + license_key) est exposée au nœud…
        $response->assertJsonPath('license.license_key', fn ($v) => is_string($v) && $v !== '');
        $response->assertJsonPath('license.signed_payload', fn ($v) => is_string($v) && str_contains($v, '.'));

        // …mais jamais metadata (hash edge_token) ni license_key du nœud.
        $response->assertJsonMissingPath('data.metadata');
        $response->assertJsonMissingPath('data.license_key');

        // edge_token montré une seule fois à l'enrôlement (design RegisterEdgeNode).
        $response->assertJsonPath('edge_token', fn ($v) => is_string($v) && strlen($v) === 64);
    }

    public function test_issue_license_exposes_signed_payload_to_node(): void
    {
        $this->actingAs($this->manager)
            ->postJson("/api/v1/edge/{$this->node->id}/license", ['valid_days' => 30])
            ->assertOk()
            ->assertJsonPath('data.edge_node_id', $this->node->id)
            ->assertJsonPath('data.signed_payload', fn ($v) => is_string($v) && str_contains($v, '.'));
    }

    public function test_serialization_default_hides_sensitive_fields(): void
    {
        $array = $this->node->toArray();
        $this->assertArrayNotHasKey('license_key', $array);
        $this->assertArrayNotHasKey('metadata', $array);

        $licenseArray = $this->license->toArray();
        $this->assertArrayNotHasKey('license_key', $licenseArray);
        $this->assertArrayNotHasKey('signed_payload', $licenseArray);
    }
}
