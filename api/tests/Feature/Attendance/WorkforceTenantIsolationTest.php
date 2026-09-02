<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC05 (#5881) — Isolation cross-tenant WORKFORCE (Attendance/Planning).
 *
 * Verrouille la non-régression de l'isolation API : un manager du tenant A ne
 * doit jamais voir ni modifier les données de présence/corrections du tenant B.
 *
 * Contexte : `AttendanceLog` et `AttendanceCorrectionRequest` n'ont PAS de
 * scope global (company_id filtré manuellement). Le correctif DEP-BC05 :
 *   - `AttendancePolicy::update` refuse désormais tout log d'un autre tenant
 *     (fail-closed cross-tenant, pattern EmployeePolicy::view #3232) ;
 *   - `AttendanceController::corrections()` filtre par `company_id` de l'acteur
 *     (la liste fuyait cross-tenant avant le correctif).
 */
class WorkforceTenantIsolationTest extends TestCase
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

    private function makeLog(Company $company, Employee $employee, int $session = 1): AttendanceLog
    {
        return AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'session_number' => $session,
        ]);
    }

    private function makeCorrection(Company $company, Employee $employee): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:12:00', 'UTC'),
            'requested_check_out' => Carbon::parse('2026-05-27 17:20:00', 'UTC'),
            'reason' => 'Oubli du pointage mobile',
            'status' => 'pending',
        ]);
    }

    public function test_manager_sees_only_own_tenants_attendance_logs(): void
    {
        $this->makeLog($this->companyA, $this->employeeA, 1);
        $this->makeLog($this->companyA, $this->employeeA, 2);
        $this->makeLog($this->companyB, $this->employeeB, 1);
        $this->makeLog($this->companyB, $this->employeeB, 2);
        $this->makeLog($this->companyB, $this->employeeB, 3);

        Sanctum::actingAs($this->managerA);

        // AttendanceLog n'a pas de scope global : le WHERE company_id explicite
        // du contrôleur est la barrière — elle doit exclure les logs du tenant B.
        $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_cannot_list_other_tenants_corrections(): void
    {
        $this->makeCorrection($this->companyA, $this->employeeA);
        $this->makeCorrection($this->companyB, $this->employeeB);
        $this->makeCorrection($this->companyB, $this->employeeB);

        Sanctum::actingAs($this->managerA);

        // Avant correctif DEP-BC05 : la liste remontait TOUTES les corrections
        // (PII : employés, dates, motifs) — le filtre company_id manquait.
        $this->getJson('/api/v1/attendance/corrections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_manager_cannot_approve_other_tenants_correction(): void
    {
        $correctionB = $this->makeCorrection($this->companyB, $this->employeeB);

        Sanctum::actingAs($this->managerA);

        // Fail-closed cross-tenant : 404 (convention anti-existence) — jamais
        // de décision sur un autre tenant, même si les IDs coïncident.
        $this->postJson("/api/v1/attendance/corrections/{$correctionB->id}/approve")
            ->assertNotFound();
    }

    public function test_manager_cannot_update_other_tenants_attendance_log(): void
    {
        $logB = $this->makeLog($this->companyB, $this->employeeB);

        Sanctum::actingAs($this->managerA);

        $this->putJson("/api/v1/attendance/{$logB->id}", [
            'notes' => 'modification cross-tenant',
        ])->assertNotFound();
    }
}
