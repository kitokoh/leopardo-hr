<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeavePolicy;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MultiLevelAbsenceApprovalTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_submitting_absence_triggers_multi_level_approval_workflow(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $absenceType = AbsenceType::factory()->create(['company_id' => $company->id, 'deducts_leave' => false]);

        // Create Leave Policy requiring approval
        LeavePolicy::create([
            'company_id' => $company->id,
            'absence_type_id' => $absenceType->id,
            'name' => 'Test Policy',
            'accrual_type' => 'yearly',
            'requires_approval' => true,
            'approval_levels' => 2,
            'active' => true,
        ]);

        // Create Approval Workflow for Absences
        ApprovalWorkflow::create([
            'company_id' => $company->id,
            'name' => 'Absence Workflow',
            'model_type' => Absence::class,
            'levels' => [
                ['level' => 1, 'approver_type' => 'manager'],
                ['level' => 2, 'approver_type' => 'hr'],
            ],
            'active' => true,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Need a break',
        ]);

        $response->assertStatus(201);
        $absenceId = $response->json('data.id');

        // Verify Absence is pending
        $this->assertDatabaseHas('absences', [
            'id' => $absenceId,
            'status' => 'pending',
        ]);

        // Verify Approval Request was created
        $this->assertDatabaseHas('approval_requests', [
            'company_id' => $company->id,
            'approvable_type' => Absence::class,
            'approvable_id' => $absenceId,
            'status' => 'pending',
            'current_level' => 1,
        ]);
    }

    public function test_legacy_approval_is_blocked_when_multi_level_workflow_is_active(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $absenceType = AbsenceType::factory()->create(['company_id' => $company->id, 'deducts_leave' => false]);

        LeavePolicy::create([
            'company_id' => $company->id,
            'absence_type_id' => $absenceType->id,
            'name' => 'Test Policy',
            'requires_approval' => true,
            'active' => true,
        ]);

        ApprovalWorkflow::create([
            'company_id' => $company->id,
            'name' => 'Absence Workflow',
            'model_type' => Absence::class,
            'levels' => [['level' => 1, 'approver_type' => 'manager']],
            'active' => true,
        ]);

        Sanctum::actingAs($employee);
        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);
        $absenceId = $response->json('data.id');

        // Manager tries to approve via legacy endpoint
        Sanctum::actingAs($manager);
        $approveResponse = $this->putJson("/api/v1/absences/{$absenceId}/approve");

        $approveResponse->assertStatus(422);
        $approveResponse->assertJsonFragment([
            'message' => 'This absence is under a multi-level approval workflow. Please use the approvals API.',
        ]);

        // Verify absence still pending
        $this->assertEquals('pending', Absence::find($absenceId)->status);
    }
}
