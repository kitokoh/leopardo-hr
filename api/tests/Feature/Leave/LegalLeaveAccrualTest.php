<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5289 — plancher légal de congés appliqué par `leave:accrue` (US1).
 *
 * L'acquisition mensuelle d'une politique de congés déductibles ne descend
 * jamais sous le minimum légal du pays de l'entreprise (ex. 2,5 j/mois DZ).
 */
class LegalLeaveAccrualTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(string $country): Company
    {
        return Company::query()->create([
            'name' => "Leave Legal {$country}",
            'slug' => 'leave-legal-'.strtolower($country),
            'sector' => 'tech',
            'country' => $country,
            'city' => 'Test City',
            'email' => "leave-legal-{$country}@test.com",
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);
    }

    private function makeEmployee(Company $company, string $matricule): Employee
    {
        $employee = new Employee([
            'matricule' => $matricule,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => strtolower($matricule).'@leave-legal.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function makeDeductibleType(Company $company, string $code = 'ANNUAL'): AbsenceType
    {
        return AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Annual Leave',
            'is_paid' => true,
            'deducts_leave' => true,
        ]);
    }

    private function makePolicy(Company $company, AbsenceType $type, float $accrualAmount): LeavePolicy
    {
        return LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => $type->id,
            'name' => 'Congés annuels',
            'accrual_type' => 'monthly',
            'accrual_amount' => $accrualAmount,
            'requires_approval' => true,
            'active' => true,
        ]);
    }

    private function runMonthlyAccrual(): void
    {
        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('leave:accrue', ['--force' => true])->assertSuccessful();
    }

    public function test_dz_sub_legal_policy_is_brought_up_to_the_2_5_days_floor(): void
    {
        $this->travelTo(Carbon::create(2026, 8, 15));

        $company = $this->makeCompany('DZ');
        $employee = $this->makeEmployee($company, 'EMP-DZ-001');
        $type = $this->makeDeductibleType($company);
        $this->makePolicy($company, $type, 1.0); // sous-légal : 1 j/mois < 2,5 j/mois

        $log = Log::spy();

        $this->runMonthlyAccrual();

        $balance = LeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(2.5, $balance->balance, 'Le plancher légal DZ (2,5 j/mois) doit s\'appliquer.');

        $accrual = LeaveAccrual::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(2.5, $accrual->amount);
        $this->assertStringContainsString('plancher légal DZ', (string) $accrual->description);

        // @phpstan-ignore-next-line staticMethod.notFound (Log::spy → MockInterface dynamique)
        $log->shouldHaveReceived('info')->once()->with('planning.legal_leave.floor_applied', \Mockery::any());
    }

    public function test_dz_policy_above_floor_is_left_unchanged(): void
    {
        $this->travelTo(Carbon::create(2026, 8, 15));

        $company = $this->makeCompany('DZ');
        $employee = $this->makeEmployee($company, 'EMP-DZ-002');
        $type = $this->makeDeductibleType($company);
        $this->makePolicy($company, $type, 3.0); // ≥ plancher : comportement inchangé

        $this->runMonthlyAccrual();

        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(3.0, $balance->balance);

        $accrual = LeaveAccrual::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(3.0, $accrual->amount);
        $this->assertStringNotContainsString('plancher légal', (string) $accrual->description);
    }

    public function test_unsupported_country_preserves_historical_behaviour(): void
    {
        // FR est supporté par le registre PAYROLL, mais pas (encore) par le
        // registre des congés légaux : comportement historique préservé.
        $this->travelTo(Carbon::create(2026, 8, 15));

        $company = $this->makeCompany('FR');
        $employee = $this->makeEmployee($company, 'EMP-FR-001');
        $type = $this->makeDeductibleType($company);
        $this->makePolicy($company, $type, 1.0);

        $this->runMonthlyAccrual();

        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(1.0, $balance->balance, 'Pays non supporté → aucun plancher.');
    }

    public function test_non_deductible_absence_type_is_not_floored(): void
    {
        $this->travelTo(Carbon::create(2026, 8, 15));

        $company = $this->makeCompany('DZ');
        $employee = $this->makeEmployee($company, 'EMP-DZ-003');
        $type = AbsenceType::query()->create([
            'company_id' => $company->id,
            'code' => 'SICK',
            'name' => 'Sick Leave',
            'is_paid' => true,
            'deducts_leave' => false, // non déductible → pas de plancher congés
        ]);
        $this->makePolicy($company, $type, 1.0);

        $this->runMonthlyAccrual();

        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(1.0, $balance->balance);
    }
}
