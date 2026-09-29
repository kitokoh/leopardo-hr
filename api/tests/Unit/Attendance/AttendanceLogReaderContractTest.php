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

    /** @return array{0: Company, 1: Employee} */
    private function seedCompanyAndEmployee(): array
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
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
            'email' => 'employee@a.test',
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
