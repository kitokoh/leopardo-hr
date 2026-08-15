<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA #2329 — le snapshot `leave_balances` (servi par GET /me/leave-balances
 * et /leave-balances) doit refléter le cycle de vie des absences :
 * create → pending += jours ; approve → pending -=, used += ;
 * reject pending → pending -= ; reject approved → used -= ;
 * cancel pending → pending -=.
 */
class LeaveBalanceSnapshotTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    private AbsenceType $type;

    private AbsenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Balance Co',
            'slug' => 'balance-co',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'balance@company.test',
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

        $this->manager = Employee::query()->create([
            'company_id' => $this->company->id,
            'schedule_id' => $schedule->id,
            'matricule' => 'MGR-BAL',
            'first_name' => 'Boss',
            'last_name' => 'RH',
            'email' => 'boss@balance.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $this->employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'schedule_id' => $schedule->id,
            'matricule' => 'EMP-BAL',
            'first_name' => 'Yacine',
            'last_name' => 'B',
            'email' => 'emp@balance.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->type = AbsenceType::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        // Solde initial : 20 jours via la chaîne leave_balance_logs (source de vérité).
        LeaveBalanceLog::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        $this->service = app(AbsenceService::class);
    }

    private function snapshot(): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->type->id)
            ->first();
    }

    private function createAbsence(int $days = 2): Absence
    {
        $start = now()->addDays(10)->format('Y-m-d');
        $end = now()->addDays(10 + $days - 1)->format('Y-m-d');

        return $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Vacances',
        ]);
    }

    public function test_create_adds_pending_days_to_snapshot(): void
    {
        $this->createAbsence(2);

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, $snapshot->used);
        $this->assertSame(2.0, $snapshot->pending);
    }

    public function test_approve_moves_pending_to_used(): void
    {
        $absence = $this->createAbsence(3);

        $this->service->approve($absence, $this->manager);

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(3.0, $snapshot->used);
        $this->assertSame(0.0, $snapshot->pending);
    }

    public function test_reject_pending_releases_pending(): void
    {
        $absence = $this->createAbsence(2);

        $this->service->reject($absence, 'Refusé');

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, $snapshot->pending);
        $this->assertSame(0.0, $snapshot->used);
    }

    public function test_reject_approved_restores_used(): void
    {
        $absence = $this->createAbsence(4);
        $this->service->approve($absence, $this->manager);

        $this->service->reject($absence, 'Annulé après approbation');

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, $snapshot->used);
        $this->assertSame(0.0, $snapshot->pending);
    }

    public function test_cancel_pending_releases_pending(): void
    {
        $absence = $this->createAbsence(2);

        $this->service->cancel($absence);

        $snapshot = $this->snapshot();
        $this->assertNotNull($snapshot);
        $this->assertSame(0.0, $snapshot->pending);
    }

    public function test_absence_type_without_deduction_does_not_touch_snapshot(): void
    {
        $nonDeducting = AbsenceType::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Formation',
            'code' => 'FOR',
            'is_paid' => true,
            'deducts_leave' => false,
            'requires_proof' => false,
        ]);

        $this->service->create($this->employee, [
            'absence_type_id' => $nonDeducting->id,
            'start_date' => now()->addDays(20)->format('Y-m-d'),
            'end_date' => now()->addDays(21)->format('Y-m-d'),
        ]);

        $this->assertNull(
            LeaveBalance::query()
                ->where('company_id', $this->company->id)
                ->where('absence_type_id', $nonDeducting->id)
                ->first()
        );
    }

    public function test_snapshot_is_company_scoped(): void
    {
        // L'absence d'un employé d'une AUTRE compagnie ne doit pas créer de
        // ligne dans la compagnie courante (isolation tenant du snapshot).
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-balance-co',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'other@company.test',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
        $otherEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'matricule' => 'EMP-OTH',
            'first_name' => 'Other',
            'last_name' => 'Emp',
            'email' => 'other@balance.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        AbsenceType::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Congé annuel',
            'code' => 'CA-OTH',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $otherType = AbsenceType::where('company_id', $otherCompany->id)->firstOrFail();

        // Solde initial pour l'employé de l'autre compagnie.
        LeaveBalanceLog::query()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        $this->service->create($otherEmployee, [
            'absence_type_id' => $otherType->id,
            'start_date' => now()->addDays(30)->format('Y-m-d'),
            'end_date' => now()->addDays(31)->format('Y-m-d'),
        ]);

        $this->assertNull($this->snapshot());
        $this->assertDatabaseHas('leave_balances', [
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'absence_type_id' => $otherType->id,
        ]);
    }
}
