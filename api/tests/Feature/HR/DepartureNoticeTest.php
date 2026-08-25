<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Récapitulatif du préavis légal (issue #5325, gap G2).
 *
 * LECTURE SEULE : la durée légale vient de la règle Payroll
 * (CountryRulesInterface::noticePeriodDays) — jamais recalculée côté HR.
 * Golden tests de référence alignés sur AlgeriaPayrollRules (22 j < 10 ans,
 * 44 j ≥ 10 ans — loi 90-11) et sur la définition d'ancienneté du moteur
 * (EndOfContractService::yearsOfService).
 */
class DepartureNoticeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_sees_dz_notice_under_10_years(): void
    {
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subYears(5)->toDateString(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.country_code', 'DZ')
            ->assertJsonPath('data.years_of_service', 5)
            ->assertJsonPath('data.notice_days', 22)
            ->assertJsonPath('data.notice_status', 'unknown')
            ->assertJsonPath('data.rule_reference', 'AlgeriaPayrollRules::noticePeriodDays()');
    }

    public function test_manager_sees_dz_notice_over_10_years(): void
    {
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subYears(12)->toDateString(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.years_of_service', 12)
            ->assertJsonPath('data.notice_days', 44);
    }

    public function test_years_of_service_matches_payroll_floor_definition(): void
    {
        // 9 ans 11 mois → 119 mois → 9.92 ans (< 10) → 22 j ; la borne est
        // franchie seulement à 120 mois entiers (même définition que
        // EndOfContractService::monthsOfService).
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subMonths(119)->toDateString(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            // 119 ou 118 mois (décalage de secondes) → toujours < 10 ans →
            // 22 j : c'est la BORNE qui compte, pas la valeur exacte.
            ->assertJsonPath('data.notice_days', 22);
    }

    public function test_country_without_notice_rule_returns_zero(): void
    {
        // Maroc : pays supporté par le résolveur, sans règle de préavis
        // documentée → défaut AbstractCountryRules (0) : le contrat décide.
        [$company, $manager, $employee] = $this->createActors([
            'country' => 'MA',
            'contract_start' => now()->subYears(5)->toDateString(),
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.country_code', 'MA')
            ->assertJsonPath('data.notice_days', 0);
    }

    public function test_employee_cannot_view_notice(): void
    {
        [$company, , $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertForbidden();
    }

    public function test_cross_tenant_notice_is_forbidden(): void
    {
        [$companyA, $managerA] = $this->createActors([], 'a');
        [, , $employeeB] = $this->createActors([], 'b');

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/employees/{$employeeB->id}/departure/notice")
            ->assertNotFound();
    }

    public function test_notice_status_served_when_departure_recorded(): void
    {
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subYears(5)->toDateString(),
        ]);

        $this->createDeparturesTable();
        $this->seedDeparture($company, $employee, noticeServed: true, daysServed: 20);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.notice_status', 'served')
            ->assertJsonPath('data.notice_days_served', 20)
            ->assertJsonPath('data.notice_days', 22);
    }

    public function test_notice_status_not_served_when_departure_recorded(): void
    {
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subYears(5)->toDateString(),
        ]);

        $this->createDeparturesTable();
        $this->seedDeparture($company, $employee, noticeServed: false, daysServed: null);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.notice_status', 'not_served')
            ->assertJsonPath('data.notice_days_served', null);
    }

    public function test_resilient_when_departures_table_absent(): void
    {
        // Avant le merge du workflow #5324, la table n'existe pas : le
        // récapitulatif reste disponible avec un statut `unknown` (fail-open).
        [$company, $manager, $employee] = $this->createActors([
            'contract_start' => now()->subYears(5)->toDateString(),
        ]);

        if (Schema::hasTable('employee_departures')) {
            Schema::drop('employee_departures');
        }

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/employees/{$employee->id}/departure/notice")
            ->assertOk()
            ->assertJsonPath('data.notice_status', 'unknown');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createActors(array $overrides = [], string $suffix = 'a'): array
    {
        $companyData = [
            'schema_name' => 'shared_tenants',
            'country' => 'DZ',
            'timezone' => 'UTC',
        ];
        if (isset($overrides['country'])) {
            $companyData['country'] = $overrides['country'];
        }

        /** @var Company $company */
        $company = Company::factory()->create($companyData);

        $manager = $this->createEmployee($company, 'manager.'.$suffix.'@notice.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.'.$suffix.'@notice.test', 'employee', null, $overrides['contract_start'] ?? null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
        ?string $contractStart = null,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();
        $employeeData = [
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ];
        if ($contractStart !== null) {
            // Défaut DB : CURRENT_DATE — ne pas écraser par NULL explicite
            // (NOT NULL violation).
            $employeeData['contract_start'] = $contractStart;
        }
        $employee->forceFill($employeeData)->save();

        /** @var Employee $employee */
        return $employee;
    }

    private function createDeparturesTable(): void
    {
        if (Schema::hasTable('employee_departures')) {
            return;
        }

        Schema::create('employee_departures', function ($table): void {
            $table->id();
            $table->string('company_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->string('departure_type')->default('resignation');
            $table->string('reason')->nullable();
            $table->date('last_work_day')->nullable();
            $table->boolean('notice_served')->default(false);
            $table->unsignedInteger('notice_days_served')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    private function seedDeparture(
        Company $company,
        Employee $employee,
        bool $noticeServed,
        ?int $daysServed,
    ): void {
        DB::table('employee_departures')->insert([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'departure_type' => 'resignation',
            'reason' => 'Test',
            'last_work_day' => Carbon::now(),
            'notice_served' => $noticeServed,
            'notice_days_served' => $daysServed,
            'last_work_day' => Carbon::now()->toDateString(),
            'departed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
