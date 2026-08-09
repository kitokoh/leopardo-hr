<?php

namespace Tests\Feature\Attendance;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class ManualUpdateTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_manual_update_recalculates_derived_fields(): void
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
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'schedule_id' => $schedule->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $log = AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-04-04',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-04 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-04 17:00:00', 'UTC'),
            'method' => 'mobile',
            'status' => 'ontime',
            'hours_worked' => 8.00,
            'overtime_hours' => 0.00,
            'late_minutes' => 0,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->putJson("/api/v1/attendance/{$log->id}", [
            'check_in' => '2026-04-04T08:30:00Z',
            'check_out' => '2026-04-04T18:30:00Z',
            'notes' => 'Correction manager',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'late');
        $response->assertJsonPath('data.hours_worked', '9.00');
        $response->assertJsonPath('data.overtime_hours', '1.00');
        $response->assertJsonPath('data.late_minutes', 15);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'method' => 'manual',
            'corrected_by' => $manager->id,
            'correction_note' => 'Correction manager',
        ]);
    }
}

