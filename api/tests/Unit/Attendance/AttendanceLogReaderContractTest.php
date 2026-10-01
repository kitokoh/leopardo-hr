<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceLogReaderAdapter;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Shared\Contracts\Attendance\AttendanceLogReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-023 cycle 2 (#8254) — contrat partagé de lecture des journaux de
 * pointage : le container résout `AttendanceLogReader` vers l'adapter
 * Attendance, et les requêtes du contrat reprennent à l'identique le
 * comportement historique (colonnes, filtres, tris d'EstimationService).
 */
class AttendanceLogReaderContractTest extends TestCase
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

    public function test_contract_resolves_to_attendance_adapter(): void
    {
        $this->assertInstanceOf(
            AttendanceLogReaderAdapter::class,
            $this->app->make(AttendanceLogReader::class),
        );
    }

    public function test_logs_for_employee_on_date_returns_views_ordered_by_session(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        foreach ([2, 1] as $session) {
            AttendanceLog::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'date' => '2026-04-06',
                'session_number' => $session,
                'check_in' => Carbon::parse('2026-04-06 09:00:00', 'UTC'),
                'check_out' => Carbon::parse('2026-04-06 12:00:00', 'UTC'),
                'hours_worked' => 3,
                'overtime_hours' => 0,
                'late_minutes' => 5,
                'method' => 'mobile',
                'status' => 'ontime',
            ]);
        }
        // Un autre jour et un autre employé ne doivent pas remonter.
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => '2026-04-07',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-07 09:00:00', 'UTC'),
            'method' => 'mobile',
            'status' => 'ontime',
        ]);

        $logs = $this->app->make(AttendanceLogReader::class)
            ->logsForEmployeeOnDate((int) $employee->id, '2026-04-06');

        $this->assertCount(2, $logs);
        // L'adapter retourne les modèles Eloquent eux-mêmes (qui implémentent
        // la vue) — zéro DTO intermédiaire, compatibilité appelants préservée.
        $this->assertInstanceOf(AttendanceLog::class, $logs[0]);
        // Tri par numéro de session croissant (sessions insérées en désordre).
        $this->assertSame(1, $logs[0]->sessionNumber());
        $this->assertSame(2, $logs[1]->sessionNumber());
        // Normalisation des casts decimal:2 → float (comportement historique).
        $this->assertSame(3.0, $logs[0]->hoursWorked());
        $this->assertSame(5, $logs[0]->lateMinutes());
        $this->assertSame('ontime', $logs[0]->status());
        $this->assertSame('2026-04-06', $logs[0]->date()?->toDateString());
    }

    public function test_logs_for_employee_between_respects_inclusive_range(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        foreach (['2026-04-05', '2026-04-06', '2026-04-07'] as $day) {
            AttendanceLog::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'date' => $day,
                'session_number' => 1,
                'check_in' => Carbon::parse("{$day} 09:00:00", 'UTC'),
                'check_out' => Carbon::parse("{$day} 17:00:00", 'UTC'),
                'hours_worked' => 8,
                'overtime_hours' => 0,
                'method' => 'mobile',
                'status' => 'ontime',
            ]);
        }

        $logs = $this->app->make(AttendanceLogReader::class)
            ->logsForEmployeeBetween((int) $employee->id, '2026-04-06', '2026-04-07');

        $this->assertCount(2, $logs);
        $this->assertSame('2026-04-06', $logs[0]->date()?->toDateString());
        $this->assertSame('2026-04-07', $logs[1]->date()?->toDateString());
    }

    // ------------------------------------------------------------------
    // BOS-023 cycle 3 (#8296) — méthodes ajoutées au contrat pour le
    // découplage HR → Attendance (requêtes historiques reprises à
    // l'identique : MobileExperienceService, EmployeeController,
    // MeController, HrReportController).
    // ------------------------------------------------------------------

    public function test_has_any_log_for_employee(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        $reader = $this->app->make(AttendanceLogReader::class);

        $this->assertFalse($reader->hasAnyLogForEmployee((string) $company->id, (int) $employee->id));

        $this->seedLog($company, $employee, '2026-04-06', 1, ['check_out' => Carbon::parse('2026-04-06 17:00:00', 'UTC')]);

        $this->assertTrue($reader->hasAnyLogForEmployee((string) $company->id, (int) $employee->id));
        // Un autre employé de la même entreprise n'a aucun journal.
        $this->assertFalse($reader->hasAnyLogForEmployee((string) $company->id, (int) $employee->id + 999));
    }

    public function test_latest_logs_per_employee_on_date_groups_and_orders(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();
        [$companyB, $other] = $this->seedCompanyAndEmployee('company-b', 'employee-b@a.test');

        // Sessions insérées en désordre : la requête trie session_number DESC
        // puis check_in DESC, le regroupement garde la première par employé.
        $this->seedLog($company, $employee, '2026-04-06', 2, ['check_in' => Carbon::parse('2026-04-06 14:00:00', 'UTC'), 'punch_meta' => ['source' => 'kiosk']]);
        $this->seedLog($company, $employee, '2026-04-06', 1, ['check_in' => Carbon::parse('2026-04-06 09:00:00', 'UTC')]);
        $this->seedLog($company, $employee, '2026-04-07', 1, []); // autre jour : exclu
        $this->seedLog($companyB, $other, '2026-04-06', 1, []);

        $latest = $this->app->make(AttendanceLogReader::class)
            ->latestLogsPerEmployeeOnDate([(int) $employee->id, (int) $other->id], '2026-04-06');

        $this->assertCount(2, $latest);
        $this->assertSame(2, $latest[(int) $employee->id]->sessionNumber());
        $this->assertSame(1, $latest[(int) $other->id]->sessionNumber());
        // Pas de projection restrictive sur cette requête : punch_meta lu.
        $this->assertSame(['source' => 'kiosk'], $latest[(int) $employee->id]->punchMeta());
        $this->assertSame('normal', $latest[(int) $employee->id]->workType());
        $this->assertNotNull($latest[(int) $employee->id]->id());
    }

    public function test_latest_log_for_employee_on_date_prefers_open_session(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();

        // Session 2 clôturée + session 1 ouverte : la session OUVERTE gagne
        // malgré un numéro inférieur (tri historique de MeController::today).
        $this->seedLog($company, $employee, '2026-04-06', 2, ['check_out' => Carbon::parse('2026-04-06 18:00:00', 'UTC')]);
        $open = $this->seedLog($company, $employee, '2026-04-06', 1, ['check_out' => null]);

        $latest = $this->app->make(AttendanceLogReader::class)
            ->latestLogForEmployeeOnDate((int) $employee->id, '2026-04-06');

        $this->assertNotNull($latest);
        $this->assertSame((int) $open->id, $latest->id());
        $this->assertSame(1, $latest->sessionNumber());
        // punch_meta n'est pas projeté par la requête today (comme avant).
        $this->assertNull($latest->punchMeta());
    }

    public function test_overtime_totals_between_aggregates_and_orders(): void
    {
        [$company, $employee] = $this->seedCompanyAndEmployee();
        [$companyB, $other] = $this->seedCompanyAndEmployee('company-b', 'employee-b@a.test');

        $this->seedLog($company, $employee, '2026-04-05', 1, ['overtime_hours' => 1.5]);
        $this->seedLog($company, $employee, '2026-04-06', 1, ['overtime_hours' => 2.0]);
        $this->seedLog($company, $employee, '2026-04-07', 1, ['overtime_hours' => 0]); // exclu (0 h)
        $this->seedLog($company, $employee, '2026-04-08', 1, ['overtime_hours' => 9.0]); // hors plage : exclu
        $this->seedLog($companyB, $other, '2026-04-06', 1, ['overtime_hours' => 4.0]);

        $totals = $this->app->make(AttendanceLogReader::class)
            ->overtimeTotalsBetween('2026-04-05', '2026-04-07');

        $this->assertCount(2, $totals);
        // Tri décroissant sur le total : other (4 h) avant employee (3,5 h).
        $this->assertSame((int) $other->id, $totals[0]['employee_id']);
        $this->assertEqualsWithDelta(4.0, (float) $totals[0]['total_overtime'], 0.001);
        $this->assertSame(1, $totals[0]['days_with_overtime']);
        $this->assertSame((int) $employee->id, $totals[1]['employee_id']);
        $this->assertEqualsWithDelta(3.5, (float) $totals[1]['total_overtime'], 0.001);
        $this->assertSame(2, $totals[1]['days_with_overtime']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedLog(Company $company, Employee $employee, string $date, int $session, array $overrides): AttendanceLog
    {
        return AttendanceLog::query()->create(array_merge([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $employee->schedule_id,
            'date' => $date,
            'session_number' => $session,
            'check_in' => Carbon::parse("{$date} 09:00:00", 'UTC'),
            'check_out' => Carbon::parse("{$date} 12:00:00", 'UTC'),
            'hours_worked' => 3,
            'overtime_hours' => 0,
            'late_minutes' => 0,
            'method' => 'mobile',
            'status' => 'ontime',
        ], $overrides));
    }

    /** @return array{0: Company, 1: Employee} */
    private function seedCompanyAndEmployee(string $slug = 'company-a', string $email = 'employee@a.test'): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => $slug,
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slug.'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Jour',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => true,
        ]);

        $employee = Employee::query()->forceCreate([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'fixed',
            'salary_base' => 17600,
            'hourly_rate' => 0,
        ]);

        return [$company, $employee];
    }
}
