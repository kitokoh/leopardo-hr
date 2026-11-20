<?php

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Couverture de `GET /api/v1/absences/stats` (#9674).
 *
 * Regression gardee : l'endpoint renvoyait 500 sur 100 % des appels
 * (`SQLSTATE[42703] column "termination_date" does not exist`) parce que le
 * calcul du taux d'absenteisme filtrait les employes actifs sur une colonne
 * inexistante. Le schema porte le statut de sortie dans `status`
 * (`active` / `suspended` / `archived` / `departed`).
 *
 * Aucun test ne couvrait cet endpoint : les 287 tests Absences/Attendance
 * etaient verts alors que l'API repondait 500.
 *
 * Horloge maitrisee (`travelTo`) : toutes les dates du fichier sont relatives
 * a l'instant fige, donc le test ne devient pas une bombe a retardement
 * (garde #8396).
 */
class AbsenceStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    /** Instant de reference : 15 avril 2026, 10h00 UTC. */
    private const NOW = '2026-04-15 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));
        $this->travelTo(Carbon::parse(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeCompany(string $slug, string $email): Company
    {
        return Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $email,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => Carbon::now()->subMonths(3)->toDateString(),
            'subscription_end' => Carbon::now()->addMonths(9)->toDateString(),
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
    }

    private function makeSchedule(Company $company): Schedule
    {
        return Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);
    }

    private function makeAbsenceType(Company $company, string $code = 'CP'): AbsenceType
    {
        return AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Conge paye',
            'code' => $code,
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }

    private function makeEmployee(
        Company $company,
        Schedule $schedule,
        string $email,
        string $role = 'employee',
        string $status = 'active',
    ): Employee {
        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();

        $attributes = [
            'company_id' => $company->id,
            'role' => $role,
            'status' => $status,
        ];

        if ($role === 'manager') {
            $attributes['manager_role'] = 'principal';
        }

        $employee->forceFill($attributes)->save();

        return $employee;
    }

    private function makeAbsence(
        Company $company,
        Employee $employee,
        AbsenceType $type,
        string $startDate,
        string $endDate,
        int $days,
        string $status = 'pending',
        ?Employee $approver = null,
    ): Absence {
        $attributes = [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_count' => $days,
            'status' => $status,
        ];

        if ($status === 'approved' && $approver !== null) {
            $attributes['approved_by'] = $approver->id;
        }

        return Absence::query()->create($attributes);
    }

    /**
     * Garde-fou principal : l'endpoint doit repondre 200 et le denominateur du
     * taux d'absenteisme ne doit compter QUE les employes `status = active`.
     *
     * Sur le code d'avant le correctif, cet appel renvoyait 500
     * (`column "termination_date" does not exist`).
     */
    public function test_manager_gets_stats_and_departed_employees_are_excluded_from_rate(): void
    {
        $company = $this->makeCompany('company-a', 'a@company.test');
        $schedule = $this->makeSchedule($company);
        $type = $this->makeAbsenceType($company);

        $manager = $this->makeEmployee($company, $schedule, 'manager@a.test', 'manager');
        $active1 = $this->makeEmployee($company, $schedule, 'active1@a.test');
        $this->makeEmployee($company, $schedule, 'active2@a.test');
        // Employe sorti : ne doit PAS entrer dans le denominateur du taux.
        $this->makeEmployee($company, $schedule, 'departed@a.test', 'employee', 'departed');

        $this->makeAbsence(
            $company,
            $active1,
            $type,
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
            3,
            'approved',
            $manager
        );

        Sanctum::actingAs($manager);

        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/v1/absences/stats?from={$from}&to={$to}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'period' => ['from', 'to'],
                'total_absences',
                'total_days',
                'by_status' => ['pending', 'approved', 'rejected'],
                'by_type',
                'top_employees',
                'absenteeism_rate',
            ],
        ]);

        $response->assertJsonPath('data.period.from', $from);
        $response->assertJsonPath('data.period.to', $to);
        $response->assertJsonPath('data.total_absences', 1);
        $response->assertJsonPath('data.total_days', 3);
        $response->assertJsonPath('data.by_status.approved', 1);
        $response->assertJsonPath('data.by_status.pending', 0);
        $response->assertJsonPath('data.by_type.0.code', 'CP');
        $response->assertJsonPath('data.by_type.0.count', 1);
        $response->assertJsonPath('data.top_employees.0.employee_id', $active1->id);

        // 3 jours / (30 j x 5/7 ouvres x 3 employes ACTIFS) x 100 = 4.67
        // Si l'employe `departed` etait compte (4), le taux serait 3.5.
        $response->assertJsonPath(
            'data.absenteeism_rate',
            static fn ($rate): bool => is_numeric($rate) && abs((float) $rate - 4.67) < 0.01
        );
    }

    public function test_stats_are_isolated_between_tenants(): void
    {
        $companyA = $this->makeCompany('company-a', 'a@company.test');
        $companyB = $this->makeCompany('company-b', 'b@company.test');

        $scheduleA = $this->makeSchedule($companyA);
        $scheduleB = $this->makeSchedule($companyB);

        $typeA = $this->makeAbsenceType($companyA, 'CP');
        $typeB = $this->makeAbsenceType($companyB, 'CP');

        $managerA = $this->makeEmployee($companyA, $scheduleA, 'manager@a.test', 'manager');
        $employeeA = $this->makeEmployee($companyA, $scheduleA, 'employee@a.test');
        $employeeB = $this->makeEmployee($companyB, $scheduleB, 'employee@b.test');

        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->startOfMonth()->addDays(2)->toDateString();

        $this->makeAbsence($companyA, $employeeA, $typeA, $start, $end, 3);
        $this->makeAbsence($companyB, $employeeB, $typeB, $start, $end, 5);

        Sanctum::actingAs($managerA);

        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/v1/absences/stats?from={$from}&to={$to}");

        $response->assertStatus(200);
        // Seule l'absence du tenant A est comptee (3 jours, pas 8).
        $response->assertJsonPath('data.total_absences', 1);
        $response->assertJsonPath('data.total_days', 3);
    }

    public function test_stats_filter_by_period(): void
    {
        $company = $this->makeCompany('company-a', 'a@company.test');
        $schedule = $this->makeSchedule($company);
        $type = $this->makeAbsenceType($company);

        $manager = $this->makeEmployee($company, $schedule, 'manager@a.test', 'manager');
        $employee = $this->makeEmployee($company, $schedule, 'employee@a.test');

        // Une absence dans le mois courant, une dans le mois precedent.
        $this->makeAbsence(
            $company,
            $employee,
            $type,
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
            3
        );
        $this->makeAbsence(
            $company,
            $employee,
            $type,
            Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            Carbon::now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString(),
            3
        );

        Sanctum::actingAs($manager);

        $currentFrom = Carbon::now()->startOfMonth()->toDateString();
        $currentTo = Carbon::now()->endOfMonth()->toDateString();
        $previousFrom = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousTo = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $current = $this->getJson("/api/v1/absences/stats?from={$currentFrom}&to={$currentTo}");
        $current->assertStatus(200);
        $current->assertJsonPath('data.total_absences', 1);
        $current->assertJsonPath('data.period.from', $currentFrom);

        $previous = $this->getJson("/api/v1/absences/stats?from={$previousFrom}&to={$previousTo}");
        $previous->assertStatus(200);
        $previous->assertJsonPath('data.total_absences', 1);
        $previous->assertJsonPath('data.period.to', $previousTo);
    }

    public function test_stats_filter_by_employee_id(): void
    {
        $company = $this->makeCompany('company-a', 'a@company.test');
        $schedule = $this->makeSchedule($company);
        $type = $this->makeAbsenceType($company);

        $manager = $this->makeEmployee($company, $schedule, 'manager@a.test', 'manager');
        $employee1 = $this->makeEmployee($company, $schedule, 'employee1@a.test');
        $employee2 = $this->makeEmployee($company, $schedule, 'employee2@a.test');

        $this->makeAbsence(
            $company,
            $employee1,
            $type,
            Carbon::now()->startOfMonth()->toDateString(),
            Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
            3
        );
        $this->makeAbsence(
            $company,
            $employee2,
            $type,
            Carbon::now()->startOfMonth()->addDays(5)->toDateString(),
            Carbon::now()->startOfMonth()->addDays(6)->toDateString(),
            2
        );

        Sanctum::actingAs($manager);

        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/v1/absences/stats?from={$from}&to={$to}&employee_id={$employee1->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_absences', 1);
        $response->assertJsonPath('data.total_days', 3);
        $response->assertJsonPath('data.top_employees.0.employee_id', $employee1->id);
    }

    public function test_stats_rejects_invalid_period_format(): void
    {
        $company = $this->makeCompany('company-a', 'a@company.test');
        $schedule = $this->makeSchedule($company);
        $manager = $this->makeEmployee($company, $schedule, 'manager@a.test', 'manager');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/absences/stats?from=01/04/2026');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
    }

    public function test_employee_cannot_access_stats(): void
    {
        $company = $this->makeCompany('company-a', 'a@company.test');
        $schedule = $this->makeSchedule($company);
        $employee = $this->makeEmployee($company, $schedule, 'employee@a.test');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/absences/stats');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/absences/stats');

        $response->assertStatus(401);
    }
}
