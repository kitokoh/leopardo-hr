<?php

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2329 — the leave_balances snapshot (used/pending columns) must stay
 * in sync with the leave_balance_logs chain across the whole absence
 * lifecycle: create → pending, approve → used, reject/cancel → restoration.
 */
class LeaveBalancesSnapshotTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    private AbsenceType $deductibleType;

    private AbsenceType $nonDeductibleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $this->deductibleType = AbsenceType::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $this->nonDeductibleType = AbsenceType::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Congé maladie',
            'code' => 'CM',
            'is_paid' => true,
            'deducts_leave' => false,
            'requires_proof' => false,
        ]);

        $this->manager = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'Manager',
            'email' => 'manager@a.test',
        ]);
        $this->manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $this->manager->forceFill([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        $this->employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'employee@a.test',
        ]);
        $this->employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $this->employee->forceFill([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();
    }

    private function createAbsence(int $daysCount = 3, bool $deductible = true, string $status = 'pending'): Absence
    {
        return Absence::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $deductible ? $this->deductibleType->id : $this->nonDeductibleType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-'.str_pad((string) $daysCount, 2, '0', STR_PAD_LEFT),
            'days_count' => $daysCount,
            'status' => $status,
        ]);
    }

    private function seedAvailableBalance(float $amount = 20.0): void
    {
        LeaveBalanceLog::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'delta' => $amount,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => $amount,
        ]);
    }

    private function snapshot(): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->deductibleType->id)
            ->where('year', 2026)
            ->first();
    }

    private function seedSnapshot(float $pending = 0.0, float $used = 0.0, float $balance = 20.0): LeaveBalance
    {
        return LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->deductibleType->id,
            'balance' => $balance,
            'used' => $used,
            'pending' => $pending,
            'year' => 2026,
        ]);
    }

    public function test_create_sets_pending_on_snapshot(): void
    {
        // Régression leave-pending-reservation : la création exige un solde
        // suffisant (422 sinon) — le solde initial doit être seedé avant.
        $this->seedAvailableBalance(20.0);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/absences', [
            'absence_type_id' => $this->deductibleType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'reason' => 'Vacances familiales',
        ])->assertStatus(201);

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot, 'leave_balances snapshot must exist after a pending absence');
        $this->assertSame(3.0, (float) $snapshot->pending);
        $this->assertSame(0.0, (float) $snapshot->used);
    }

    public function test_approve_moves_pending_to_used(): void
    {
        $absence = $this->createAbsence(3);
        $this->seedAvailableBalance(20.0);
        $this->seedSnapshot(pending: 3.0, used: 0.0);

        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/absences/'.$absence->id.'/approve')->assertOk();

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->pending);
        $this->assertSame(3.0, (float) $snapshot->used);
    }

    public function test_reject_pending_releases_pending_days(): void
    {
        $absence = $this->createAbsence(2);
        $this->seedSnapshot(pending: 2.0, used: 0.0);

        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Refusé',
        ])->assertOk();

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->pending);
        $this->assertSame(0.0, (float) $snapshot->used);
    }

    public function test_reject_approved_restores_used_days(): void
    {
        $absence = $this->createAbsence(3, true, 'approved');
        $this->seedSnapshot(pending: 0.0, used: 3.0);

        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/absences/'.$absence->id.'/reject', [
            'rejected_reason' => 'Annulé après approbation',
        ])->assertOk();

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->pending);
        $this->assertSame(0.0, (float) $snapshot->used);
    }

    public function test_cancel_releases_pending_days(): void
    {
        $absence = $this->createAbsence(4);
        $this->seedSnapshot(pending: 4.0, used: 0.0);

        Sanctum::actingAs($this->employee);

        $this->deleteJson('/api/v1/absences/'.$absence->id)->assertOk();

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, (float) $snapshot->pending);
        $this->assertSame(0.0, (float) $snapshot->used);
    }

    public function test_non_deductible_type_never_touches_snapshot(): void
    {
        $absence = $this->createAbsence(3, false);

        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/absences/'.$absence->id.'/approve')->assertOk();

        $this->assertNull($this->snapshot());
    }

    public function test_snapshot_is_isolated_between_tenants(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $absence = $this->createAbsence(3);
        $this->seedAvailableBalance(20.0);
        $absence->update(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($this->manager);

        // Isolation cross-tenant : l'absence appartient désormais au tenant B,
        // le manager A ne peut PAS l'approuver → 404 (pas 200). La row snapshot
        // du tenant A reste introuvable.
        $this->postJson('/api/v1/absences/'.$absence->id.'/approve')->assertNotFound();
        $this->assertNull($this->snapshot());
    }
}
