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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC06 (#5882) — Isolation cross-tenant et invariants du workflow congés.
 *
 * Verrouille : (1) un manager ne voit/décide jamais sur les absences d'un
 * autre tenant (404 fail-closed) ; (2) l'approbation d'une absence
 * `deducts_leave` déduit le solde exactement une fois (snapshot
 * `leave_balances`, une seule ligne de log, double approbation → 422).
 */
class LeaveCrossTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->companyA, $this->managerA, $this->employeeA] = $this->tenant('tenant-a', 'a.test');
        [$this->companyB, , $this->employeeB] = $this->tenant('tenant-b', 'b.test');
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function tenant(string $slug, string $domain): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'email' => 'employee@'.$domain,
            'first_name' => 'Emp',
            'last_name' => ucfirst($slug),
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $manager = new Employee([
            'schedule_id' => $schedule->id,
            'email' => 'manager@'.$domain,
            'first_name' => 'Mgr',
            'last_name' => ucfirst($slug),
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return [$company, $manager, $employee];
    }

    private function makeType(Company $company, bool $deductsLeave = false): AbsenceType
    {
        // `absence_types_code_unique` est global (non scopé par tenant) : les
        // codes doivent être uniques dans TOUTE la base, d'où le suffixe tenant.
        $suffix = $company->slug;
        return AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => $deductsLeave ? 'Congé payé' : 'Mission',
            'code' => $deductsLeave ? 'paid_leave_'.$suffix : 'mission_'.$suffix,
            'deducts_leave' => $deductsLeave,
        ]);
    }

    private function makeAbsence(Company $company, Employee $employee, AbsenceType $type): Absence
    {
        return Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'days_count' => 2,
            'status' => 'pending',
            'reason' => 'Test',
        ]);
    }

    private function seedBalance(Company $company, Employee $employee, AbsenceType $type, float $days = 10): LeaveBalance
    {
        return LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'year' => 2026,
            'balance' => $days,
            'used' => 0,
            'pending' => 0,
        ]);
    }

    public function test_manager_lists_only_own_tenants_absences(): void
    {
        $typeA = $this->makeType($this->companyA);
        $typeB = $this->makeType($this->companyB);
        $this->makeAbsence($this->companyA, $this->employeeA, $typeA);
        $this->makeAbsence($this->companyB, $this->employeeB, $typeB);
        $this->makeAbsence($this->companyB, $this->employeeB, $typeB);

        Sanctum::actingAs($this->managerA);

        $this->getJson('/api/v1/absences')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_cannot_show_other_tenants_absence(): void
    {
        $typeB = $this->makeType($this->companyB);
        $absenceB = $this->makeAbsence($this->companyB, $this->employeeB, $typeB);

        Sanctum::actingAs($this->managerA);

        $this->getJson("/api/v1/absences/{$absenceB->id}")->assertNotFound();
    }

    public function test_manager_cannot_approve_other_tenants_absence(): void
    {
        $typeB = $this->makeType($this->companyB);
        $absenceB = $this->makeAbsence($this->companyB, $this->employeeB, $typeB);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/absences/{$absenceB->id}/approve")->assertNotFound();
    }

    public function test_approval_deducts_leave_balance_exactly_once(): void
    {
        $type = $this->makeType($this->companyA, true);
        $absence = $this->makeAbsence($this->companyA, $this->employeeA, $type);
        $this->seedBalance($this->companyA, $this->employeeA, $type, 10.0);

        Sanctum::actingAs($this->managerA);

        $this->postJson("/api/v1/absences/{$absence->id}/approve")->assertOk();

        $balance = LeaveBalance::query()
            ->where('company_id', $this->companyA->id)
            ->where('employee_id', $this->employeeA->id)
            ->where('absence_type_id', $type->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(8.0, (float) $balance->balance - (float) $balance->used);
        $this->assertSame(1, LeaveBalanceLog::query()
            ->where('company_id', $this->companyA->id)
            ->where('employee_id', $this->employeeA->id)
            ->count());

        $this->assertSame('approved', $absence->refresh()->status);

        // Double approbation → l'invariant pending-only refuse (422).
        $this->postJson("/api/v1/absences/{$absence->id}/approve")->assertStatus(422);
    }
}
