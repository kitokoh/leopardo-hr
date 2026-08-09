<?php

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-ATT-009: a "bienveillant" (non-blocking) geofence alert is sent to the
 * employee's manager whenever a check-in/check-out lands outside the
 * configured company/site geofence. The punch itself is never blocked
 * (already covered by CheckInTest::test_check_in_returns_soft_geofence_context_without_blocking_outside_site),
 * this suite only covers the manager-notification side of the ticket.
 */
class AttendanceGeofenceAlertTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompanyWithGeofence(): Company
    {
        return Company::query()->create([
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
            'currency' => 'DZD',
            'timezone' => 'UTC',
            'metadata' => [
                'attendance_geofence' => [
                    'lat' => 36.7538,
                    'lng' => 3.0588,
                    'radius_meters' => 100,
                ],
            ],
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

    public function test_direct_manager_is_alerted_when_employee_checks_in_outside_the_geofence(): void
    {
        $company = $this->makeCompanyWithGeofence();
        $schedule = $this->makeSchedule($company);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'manager_id' => $manager->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        $this->postJson('/api/v1/attendance/check-in', [
            // ~1.6km away from the configured geofence center, well beyond the 100m radius.
            'gps_lat' => 36.7700,
            'gps_lng' => 3.0588,
        ])->assertStatus(201)
            ->assertJsonPath('data.geofence.inside', false);

        $log = AttendanceLog::query()->firstOrFail();

        $notification = Notification::query()->where('employee_id', $manager->id)->first();
        $this->assertNotNull($notification, 'The direct manager should have been alerted about the out-of-zone check-in.');
        $this->assertSame('attendance', $notification->type);
        $this->assertSame($log->id, $notification->data['attendance_log_id'] ?? null);
        $this->assertSame($employee->id, $notification->data['employee_id'] ?? null);

        // The employee themself is never notified about their own punch.
        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());
    }

    public function test_no_alert_is_sent_when_the_check_in_is_inside_the_geofence(): void
    {
        $company = $this->makeCompanyWithGeofence();
        $schedule = $this->makeSchedule($company);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'manager_id' => $manager->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        $this->postJson('/api/v1/attendance/check-in', [
            'gps_lat' => 36.7538,
            'gps_lng' => 3.0588,
        ])->assertStatus(201)
            ->assertJsonPath('data.geofence.inside', true);

        $this->assertSame(0, Notification::query()->where('employee_id', $manager->id)->count());
    }

    public function test_no_alert_is_sent_when_gps_is_unavailable(): void
    {
        $company = $this->makeCompanyWithGeofence();
        $schedule = $this->makeSchedule($company);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'manager_id' => $manager->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        // No gps_lat/gps_lng at all: geofence stays "configured" but "inside" is
        // null (unknown), not false. The punch is never blocked (PA2-ATT-009's
        // "tolerant GPS indisponible" requirement) and no alert should fire
        // either, since we genuinely don't know whether the employee was
        // outside the zone or not.
        $this->postJson('/api/v1/attendance/check-in', [])
            ->assertStatus(201)
            ->assertJsonPath('data.geofence.configured', true)
            ->assertJsonPath('data.geofence.inside', null);

        $this->assertSame(0, Notification::query()->where('employee_id', $manager->id)->count());
    }

    public function test_company_wide_manager_is_alerted_when_employee_has_no_direct_manager(): void
    {
        $company = $this->makeCompanyWithGeofence();
        $schedule = $this->makeSchedule($company);

        $principal = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'principal@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $rh = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'rh@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        // A non-eligible manager role (comptable) that must not receive this alert.
        Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'comptable@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'comptable',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            // No manager_id assigned.
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        $this->postJson('/api/v1/attendance/check-in', [
            'gps_lat' => 36.7700,
            'gps_lng' => 3.0588,
        ])->assertStatus(201)
            ->assertJsonPath('data.geofence.inside', false);

        $this->assertSame(1, Notification::query()->where('employee_id', $principal->id)->count());
        $this->assertSame(1, Notification::query()->where('employee_id', $rh->id)->count());
    }

    public function test_manager_is_alerted_on_out_of_geofence_check_out_too(): void
    {
        $company = $this->makeCompanyWithGeofence();
        $schedule = $this->makeSchedule($company);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'dept',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'manager_id' => $manager->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));

        // Check in inside the zone: no alert yet.
        $this->postJson('/api/v1/attendance/check-in', [
            'gps_lat' => 36.7538,
            'gps_lng' => 3.0588,
        ])->assertStatus(201);

        $this->assertSame(0, Notification::query()->where('employee_id', $manager->id)->count());

        $this->travelTo(Carbon::parse('2026-04-04 17:00:00', 'UTC'));

        // Check out outside the zone: manager gets alerted about the check-out.
        $this->postJson('/api/v1/attendance/check-out', [
            'gps_lat' => 36.7700,
            'gps_lng' => 3.0588,
        ])->assertStatus(200)
            ->assertJsonPath('data.geofence.inside', false);

        $this->assertSame(1, Notification::query()->where('employee_id', $manager->id)->count());
    }
}
