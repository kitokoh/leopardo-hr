<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\ApprovalDecision;
use App\Modules\Attendance\Domain\Models\ApprovalRequest;
use App\Modules\Attendance\Domain\Models\ApprovalWorkflow;
use App\Modules\Planning\Domain\Models\Absence; // modèle approvable réel (Planning)
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA pass 2026-08-14 (#2175) — GET /approvals/pending 500 :
 * `ApprovalRequest::query()->pending()` appelait un scope inexistant →
 * « Call to undefined method Builder::pending() ». Le FrontendApiContractTest
 * ne vérifie que l'existence de la route, pas le runtime.
 */
class ApprovalControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function makeApprovalRequest(string $status): ApprovalRequest
    {
        /** @var ApprovalWorkflow $workflow */
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->manager->company_id,
            'name' => 'Validation conge',
            'model_type' => Absence::class,
            'levels' => [['level' => 1, 'approver_role' => 'principal']],
            'active' => true,
        ]);

        /** @var ApprovalRequest $request */
        $request = ApprovalRequest::create([
            'company_id' => $this->manager->company_id,
            'workflow_id' => $workflow->id,
            'approvable_type' => Absence::class,
            'approvable_id' => 1,
            'requester_id' => $this->employee->id,
            'current_level' => 1,
            'status' => $status,
        ]);

        return $request;
    }

    public function test_pending_returns_only_pending_requests(): void
    {
        $this->makeApprovalRequest('pending');
        $this->makeApprovalRequest('approved');

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/approvals/pending')
            ->assertOk();

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('pending', $items[0]['status']);
    }

    public function test_pending_is_tenant_scoped(): void
    {
        $this->makeApprovalRequest('pending');

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($otherManager)
            ->getJson('/api/v1/approvals/pending')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_approve_and_reject_transitions(): void
    {
        $pending = $this->makeApprovalRequest('pending');

        $this->actingAs($this->manager)
            ->postJson("/api/v1/approvals/{$pending->id}/approve", ['comment' => 'OK'])
            ->assertOk();

        $this->assertSame('approved', $pending->fresh()?->status);
        $this->assertDatabaseHas('approval_decisions', [
            'approval_request_id' => $pending->id,
            'decision' => 'approved',
        ]);

        $pending2 = $this->makeApprovalRequest('pending');
        $this->actingAs($this->manager)
            ->postJson("/api/v1/approvals/{$pending2->id}/reject", ['comment' => 'Refuse'])
            ->assertOk();

        $this->assertSame('rejected', $pending2->fresh()?->status);
    }

    public function test_plain_employee_cannot_approve_or_reject(): void
    {
        // QA #3146 — la policy ApprovalRequestPolicy n'était jamais invoquée :
        // tout employé authentifié pouvait approuver/rejeter. Elle exige manager.
        $pending = $this->makeApprovalRequest('pending');

        $this->actingAs($this->employee)
            ->postJson("/api/v1/approvals/{$pending->id}/approve", ['comment' => 'Nope'])
            ->assertForbidden();

        $this->assertSame('pending', $pending->fresh()?->status);

        $pending2 = $this->makeApprovalRequest('pending');
        $this->actingAs($this->employee)
            ->postJson("/api/v1/approvals/{$pending2->id}/reject", ['comment' => 'Nope'])
            ->assertForbidden();

        $this->assertSame('pending', $pending2->fresh()?->status);
    }

    public function test_history_returns_data_meta_envelope(): void
    {
        // #4688 (audit 360° 2026-08-16) : le paginator brut renvoyait les
        // attributs modèles à plat — la forme standard {data, links, meta}
        // (resource collection) doit s'appliquer comme sur pending/approve.
        $request = $this->makeApprovalRequest('approved');

        ApprovalDecision::create([
            'approval_request_id' => $request->id,
            'level' => 1,
            'approver_id' => $this->manager->id,
            'decision' => 'approved',
            'comment' => 'Validé',
            'decided_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/approvals/history')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'approval_request_id',
                    'decision',
                    'comment',
                    'decided_at',
                    'request' => ['id', 'status', 'approvable_type', 'approvable_id', 'requester' => ['id', 'first_name', 'last_name']],
                ]],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('data.0.request.status', 'approved')
            ->assertJsonPath('data.0.comment', 'Validé')
            ->assertJsonPath('meta.total', 1);
    }
}
