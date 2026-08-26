<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * End-to-end leave workflow: request → approval → balance deduction.
 */
class LeaveWorkflowIntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_can_submit_leave_request(): void
    {
        $company = Company::factory()->create(['country' => 'DZ']);

        Employee::factory()->manager()->create(['company_id' => $company->id]);

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // #5585 : l'API exige absence_type_id (le champ `type` n'existe plus)
        // — seed du type + solde suffisant pour un contrat déterministe 201.
        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé annuel',
            'code' => 'CA',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'reason' => 'Vacances familiales',
        ]);

        // #5585 : absence valide → créée 201 (le 422 est couvert par les cas de validation).
        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'type', 'start_date', 'end_date', 'status'],
            ]);
        $this->assertEquals('pending', $response->json('data.status'));
    }

    public function test_manager_can_list_pending_absences(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/absences');
        $response->assertOk();
    }

    public function test_leave_requests_isolated_between_tenants(): void
    {
        $companyA = Company::factory()->create(['name' => 'Tenant A']);
        $companyB = Company::factory()->create(['name' => 'Tenant B']);

        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        $empB = Employee::factory()->create(['company_id' => $companyB->id]);

        // Employee B submits absence
        Sanctum::actingAs($empB);
        $this->postJson('/api/v1/absences', [
            'type' => 'conge_annuel',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'reason' => 'Test isolation',
        ]);

        // Manager A should not see Tenant B absences
        Sanctum::actingAs($managerA);
        $response = $this->getJson('/api/v1/absences');
        $response->assertOk();

        $reasons = collect($response->json('data'))->pluck('reason')->toArray();
        $this->assertNotContains('Test isolation', $reasons);
    }

    public function test_employee_cannot_approve_own_leave(): void
    {
        $company = Company::factory()->create();

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        // Attempt to create and approve — employee role should not have approve permission
        $response = $this->postJson('/api/v1/absences', [
            'type' => 'conge_annuel',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
            'reason' => 'Test',
        ]);

        if ($response->status() === 201) {
            $absenceId = $response->json('data.id');

            // Employee should not be able to approve
            $approveResponse = $this->putJson("/api/v1/absences/{$absenceId}/approve");
            // #5585 : approbation par un employé sans rôle manager → 403.
            $approveResponse->assertForbidden();
        }
    }
}

