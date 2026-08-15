<?php

declare(strict_types=1);

namespace Tests\Feature\Edge;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #3319 — Émission de licence Edge :
 * POST /api/v1/edge/{nodeId}/license est réservé aux managers et
 * valid_days est borné (1..3650).
 */
class EdgeLicenseIssuanceGuardTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    private EdgeNode $node;

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

        $this->node = EdgeNode::create([
            'company_id' => $this->company->id,
            'name' => 'Site Test',
            'slug' => 'site-test-'.substr((string) str()->uuid(), 0, 8),
            'status' => 'active',
            'mode' => 'hybrid',
            'edge_version' => '1.0.0',
            'capabilities' => ['features' => ['attendance'], 'max_employees' => 50],
            'metadata' => [],
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS public.edge_nodes CASCADE');
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_issue_license_by_plain_employee_is_forbidden(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson("/api/v1/edge/{$this->node->id}/license", [
                'valid_days' => 30,
            ]);

        $response->assertForbidden();
    }

    public function test_issue_license_rejects_out_of_bounds_valid_days(): void
    {
        foreach ([0, -1, 999999] as $days) {
            $response = $this->actingAs($this->manager, 'sanctum')
                ->postJson("/api/v1/edge/{$this->node->id}/license", [
                    'valid_days' => $days,
                ]);

            $response->assertStatus(422);
        }
    }

    public function test_issue_license_by_manager_ok(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/edge/{$this->node->id}/license", [
                'valid_days' => 90,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.edge_node_id', $this->node->id);
    }

    public function test_issue_license_defaults_to_configured_validity(): void
    {
        config(['edge.license_validity_days' => 30]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/edge/{$this->node->id}/license", []);

        $response->assertOk();
    }
}
