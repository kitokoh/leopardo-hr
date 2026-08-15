<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2329 — le snapshot leave_balances (servi par /me/leave-balances)
 * n'était jamais synchronisé : used/pending restaient à 0 après approbation.
 * La chaîne leave_balance_logs reste la source de vérité ; le snapshot est
 * désormais mis à jour par AbsenceService sur create/approve/reject/cancel.
 */
class LeaveBalanceSnapshotTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_pending_request_reserves_days_in_snapshot(): void
    {
        [$company, $manager, $employee, $type] = $this->actors();

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/absences', [
            'absence_type_id' => $type->id,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-21',
            'reason' => 'Congés annuels',
        ])->assertCreated();

        $snapshot = $this->snapshot($company, $employee, $type);
        $this->assertNotNull($snapshot, 'leave_balances row must exist after a pending request');
        $this->assertSame(2.0, $snapshot->pending);
        $this->assertSame(0.0, $snapshot->used);
    }

    public function test_approve_moves_pending_to_used(): void
    {
        [$company, $manager, $employee, $type] = $this->actors();

        $absence = $this->createAbsence($employee, $type);

        Sanctum::actingAs($manager);
        $this->putJson('/api/v1/absences/'.$absence->id.'/approve')->assertOk();

        $snapshot = $this->snapshot($company, $employee, $type);
        $this->assertSame(0.0, $snapshot->pending);
        $this->assertSame(2.0, $snapshot->used);
    }

    public function test_reject_pending_releases_pending(): void
    {
        [$company, $manager, $employee, $type] = $this->actors();

        $absence = $this->createAbsence($employee, $type);

        Sanctum::actingAs($manager);
        $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Période chargée',
        ])->assertOk();

        $snapshot = $this->snapshot($company, $employee, $type);
        $this->assertSame(0.0, $snapshot->pending);
        $this->assertSame(0.0, $snapshot->used);
    }

    public function test_reject_approved_restores_used(): void
    {
        [$company, $manager, $employee, $type] = $this->actors();

        $absence = $this->createAbsence($employee, $type);

        Sanctum::actingAs($manager);
        $this->putJson('/api/v1/absences/'.$absence->id.'/approve')->assertOk();
        $this->putJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Annulation après approbation',
        ])->assertOk();

        $snapshot = $this->snapshot($company, $employee, $type);
        $this->assertSame(0.0, $snapshot->pending);
        $this->assertSame(0.0, $snapshot->used);
    }

    public function test_cancel_pending_releases_pending(): void
    {
        [$company, $manager, $employee, $type] = $this->actors();

        $absence = $this->createAbsence($employee, $type);

        Sanctum::actingAs($employee);
        $this->deleteJson('/api/v1/absences/'.$absence->id)->assertOk();

        $snapshot = $this->snapshot($company, $employee, $type);
        $this->assertSame(0.0, $snapshot->pending);
        $this->assertSame(0.0, $snapshot->used);
    }

    public function test_non_deductible_type_does_not_touch_snapshot(): void
    {
        [$company, $manager, $employee] = $this->actors();
        $type = AbsenceType::factory()->nonDeductible()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/absences', [
            'absence_type_id' => $type->id,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-21',
        ])->assertCreated();

        $this->assertNull($this->snapshot($company, $employee, $type));
    }

    public function test_snapshot_isolated_between_tenants(): void
    {
        [$companyA, $managerA, $employeeA, $typeA] = $this->actors('A');
        [, $managerB, $employeeB, $typeB] = $this->actors('B');

        $absence = $this->createAbsence($employeeA, $typeA);

        Sanctum::actingAs($managerB);
        $this->putJson('/api/v1/absences/'.$absence->id.'/approve')->assertNotFound();

        $snapshotA = $this->snapshot($companyA, $employeeA, $typeA);
        $this->assertNotNull($snapshotA);
        $this->assertSame(2.0, $snapshotA->pending);

        $snapshotB = $this->snapshotFor($companyA->id, $employeeB->id, $typeB->id);
        $this->assertNull($snapshotB);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: AbsenceType}
     */
    private function actors(string $suffix = 'A'): array
    {
        $company = Company::factory()->create([
            'name' => 'Leave Co '.$suffix,
            'slug' => 'leave-co-'.strtolower($suffix),
        ]);
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'email' => 'manager-'.strtolower($suffix).'@leave.test',
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.strtolower($suffix).'@leave.test',
        ]);
        $type = AbsenceType::factory()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP-'.strtoupper($suffix),
        ]);

        return [$company, $manager, $employee, $type];
    }

    private function createAbsence(Employee $employee, AbsenceType $type): \App\Modules\Planning\Domain\Models\Absence
    {
        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $type->id,
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-21',
            'reason' => 'Congés annuels',
        ]);
        $response->assertCreated();

        return \App\Modules\Planning\Domain\Models\Absence::query()->findOrFail($response->json('data.id'));
    }

    private function snapshot(Company $company, Employee $employee, AbsenceType $type): ?LeaveBalance
    {
        return $this->snapshotFor($company->id, $employee->id, $type->id);
    }

    private function snapshotFor(int|string $companyId, int $employeeId, int $typeId): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('absence_type_id', $typeId)
            ->where('year', 2026)
            ->first();
    }
}
