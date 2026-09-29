<?php

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class AttendanceAnomaliesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_can_view_attendance_anomaly_summary(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Samir']);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'first_name' => 'Nadia']);

        app()->instance('current_company', $company);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:35:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-07',
            'check_in' => Carbon::parse('2026-05-07 08:00:00', 'UTC'),
            'check_out' => null,
            'status' => 'incomplete',
        ]);

        AttendanceLog::factory()->manual()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-08',
            'check_in' => Carbon::parse('2026-05-08 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-08 17:00:00', 'UTC'),
            'corrected_by' => $manager->id,
        ]);

        AttendanceLog::factory()->withOvertime(4.5)->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-09',
            'check_in' => Carbon::parse('2026-05-09 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-09 21:30:00', 'UTC'),
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-10',
            'check_in' => Carbon::parse('2026-05-10 08:00:00', 'UTC'),
            'source_device_code' => 'KIOSK-01',
            'method' => 'biometric',
        ]);

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-10',
            'check_in' => Carbon::parse('2026-05-10 08:00:03', 'UTC'),
            'source_device_code' => 'KIOSK-01',
            'method' => 'biometric',
        ]);

        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-10');

        $response->assertOk();
        $response->assertJsonPath('data.period.date_from', '2026-05-06');
        $response->assertJsonPath('data.period.date_to', '2026-05-10');
        $response->assertJsonPath('data.summary.total', 8);
        $response->assertJsonPath('data.summary.by_type.late_arrival', 1);
        $response->assertJsonPath('data.summary.by_type.missing_check_out', 1);
        $response->assertJsonPath('data.summary.by_type.manual_correction', 1);
        $response->assertJsonPath('data.summary.by_type.excessive_overtime', 1);
        $response->assertJsonPath('data.summary.by_type.rapid_device_sequence', 1);
        $response->assertJsonPath('data.summary.by_type.repeated_exact_check_in', 3);
        $response->assertJsonPath('data.summary.business_impact.late_minutes', 20);
        $response->assertJsonPath('data.summary.business_impact.missing_check_outs', 1);
        $response->assertJsonPath('data.summary.business_impact.manual_corrections', 1);
        $response->assertJsonPath('data.items.0.requires_manager_action', true);
        $this->assertNotEmpty($response->json('data.items.0.recommended_action'));
    }

    public function test_employee_cannot_view_attendance_anomalies(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/attendance/anomalies')->assertStatus(403);
    }

    public function test_attendance_anomalies_are_scoped_to_manager_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        app()->instance('current_company', $companyB);
        AttendanceLog::factory()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:35:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-06 17:00:00', 'UTC'),
            'late_minutes' => 60,
            'status' => 'late',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-06');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 0);
        $response->assertJsonCount(0, 'data.items');
    }

    public function test_attendance_anomalies_include_logs_dated_exactly_on_the_date_to_boundary(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-10',
            'check_in' => Carbon::parse('2026-05-10 09:20:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-10 17:00:00', 'UTC'),
            'late_minutes' => 20,
            'status' => 'late',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        // date_from and date_to are the same single day: the log dated on that
        // exact day must be included in the summary (regression for PA2-ATT-011).
        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-10&date_to=2026-05-10');

        $response->assertOk();
        $response->assertJsonPath('data.summary.total', 1);
        $response->assertJsonPath('data.summary.by_type.late_arrival', 1);
    }

    public function test_attendance_anomalies_include_geofence_and_repeated_exact_check_ins(): void
    {
        $company = Company::factory()->create([
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7525,
                    'lng' => 3.0420,
                    'radius_meters' => 100,
                ],
            ],
        ]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);
        foreach (['2026-05-06', '2026-05-07', '2026-05-08'] as $date) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'check_in' => Carbon::parse($date.' 08:00:00', 'UTC'),
                'check_out' => Carbon::parse($date.' 17:00:00', 'UTC'),
            ]);
        }

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-09',
            'check_in' => Carbon::parse('2026-05-09 08:30:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-09 17:00:00', 'UTC'),
            'gps_lat' => 36.9000,
            'gps_lng' => 3.2000,
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-06&date_to=2026-05-09');

        $response->assertOk();
        $response->assertJsonPath('data.summary.by_type.repeated_exact_check_in', 3);
        $response->assertJsonPath('data.summary.by_type.out_of_geofence', 1);
    }

    /**
     * #8179 (famille A) — robustesse de l'analyse sur donnée dégradée : un
     * pointage sans `check_in` (colonne nullable en base) est exclu des
     * détecteurs (filtre + gardes), les anomalies réelles restent détectées.
     * Note : `date` est NOT NULL en base — la garde sur `date` est défense en
     * profondeur (narrowing PHPStan), non testable via la base.
     */
    public function test_anomaly_summary_ignores_logs_without_check_in(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id]);

        // Deux pointages valides qui déclenchent rapid_device_sequence…
        foreach ([$employeeA, $employeeB] as $index => $employee) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => '2026-05-10',
                'check_in' => Carbon::parse('2026-05-10 08:00:0'.$index, 'UTC'),
                'source_device_code' => 'KIOSK-01',
                'method' => 'biometric',
            ]);
        }

        // … plus une donnée dégradée : même appareil, mais check_in NULL
        // (session 2 — unicité employee/date/session_number).
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-05-10',
            'session_number' => 2,
            'check_in' => null,
            'check_out' => null,
            'source_device_code' => 'KIOSK-01',
            'method' => 'biometric',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/anomalies?date_from=2026-05-10&date_to=2026-05-10');

        $response->assertOk();
        // L'anomalie réelle reste détectée, la ligne dégradée est ignorée.
        $response->assertJsonPath('data.summary.by_type.rapid_device_sequence', 1);
    }

    /**
     * Régression #8179 (famille A) : un check-out sur un pointage `incomplete`
     * SANS `check_in` (donnée dégradée) plantait en `->copy() on null` (500).
     * Décision métier : pas d'évaluation de retard sans heure d'arrivée — le
     * pointage conserve son statut `incomplete`, le check-out aboutit.
     */
    public function test_check_out_on_log_without_check_in_does_not_crash(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $schedule = \App\Modules\Planning\Domain\Models\Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'schedule_id' => $schedule->id,
        ]);

        $this->travelTo('2026-05-10 17:30:00');

        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-10',
            'check_in' => null,
            'check_out' => null,
            'status' => 'incomplete',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/attendance/check-out', ['gps_lat' => 36.75, 'gps_lng' => 3.05])
            ->assertOk();

        $log = AttendanceLog::query()->firstOrFail();
        $this->assertNotNull($log->check_out);
        // Jamais d'évaluation de retard sans check_in : le statut est conservé.
        $this->assertSame('incomplete', $log->status);
        $this->assertSame(0, (int) $log->late_minutes);
    }
}
