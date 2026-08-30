<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Intégration Attendance / présence opérateur FuelStation — FUEL-006
 * (issue #5800).
 *
 * Couvre : rostre manager (present/late/absent selon attendance_logs,
 * outside_shift quand le check_in sort de la fenêtre du shift), self-service
 * pompiste, isolation tenant 404, RBAC employé 403.
 */
class FuelPresenceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompanyWithTimezone(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true], 'timezone' => 'Africa/Algiers']);

        return $company;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/me/presence?date=2026-09-01')->assertStatus(401);
    }

    public function test_operator_cannot_read_shift_roster(): void
    {
        $company = $this->makeCompanyWithTimezone();
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}/presence?date=2026-09-01")->assertStatus(403);
    }

    public function test_manager_gets_presence_roster_from_attendance_logs(): void
    {
        $company = $this->makeCompanyWithTimezone();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $presentOperator */
        $presentOperator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $lateOperator */
        $lateOperator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $absentOperator */
        $absentOperator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        foreach ([$presentOperator, $lateOperator, $absentOperator] as $employee) {
            FuelShiftAssignment::query()->create([
                'company_id' => $company->id,
                'shift_id' => $shift->id,
                'employee_id' => $employee->id,
                'assignment_date' => '2026-09-01',
            ]);
        }

        // Source canonique Attendance : statut ontime / late / (aucune ligne).
        DB::table('attendance_logs')->insert([
            [
                'company_id' => $company->id,
                'employee_id' => $presentOperator->id,
                'date' => '2026-09-01',
                'check_in' => '2026-09-01 06:02:00+00',
                'check_out' => '2026-09-01 14:00:00+00',
                'status' => 'ontime',
                'late_minutes' => 0,
                'hours_worked' => 7.5,
                'method' => 'mobile',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'employee_id' => $lateOperator->id,
                'date' => '2026-09-01',
                'check_in' => '2026-09-01 07:30:00+00',
                'check_out' => '2026-09-01 14:00:00+00',
                'status' => 'late',
                'late_minutes' => 90,
                'hours_worked' => 6.5,
                'method' => 'mobile',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}/presence?date=2026-09-01")
            ->assertStatus(200)
            ->assertJsonPath('data.date', '2026-09-01')
            ->assertJsonCount(3, 'data.presence');

        /** @var list<array<string, mixed>> $presenceRows */
        $presenceRows = $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}/presence?date=2026-09-01")->json('data.presence');
        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = collect($presenceRows)->keyBy('employee_id')->all();

        $this->assertSame('present', $byEmployee[$presentOperator->id]['status']);
        $this->assertSame('late', $byEmployee[$lateOperator->id]['status']);
        $this->assertSame('absent', $byEmployee[$absentOperator->id]['status']);
        $this->assertFalse($byEmployee[$presentOperator->id]['outside_shift']);
    }

    public function test_check_in_outside_shift_window_flags_outside_shift(): void
    {
        $company = $this->makeCompanyWithTimezone();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        FuelShiftAssignment::query()->create([
            'company_id' => $company->id,
            'shift_id' => $shift->id,
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ]);

        // Pointage à 16:00 UTC = 17:00 Africa/Algiers — hors fenêtre [06:00, 14:00].
        DB::table('attendance_logs')->insert([
            'company_id' => $company->id,
            'employee_id' => $operator->id,
            'date' => '2026-09-01',
            'check_in' => '2026-09-01 16:00:00+00',
            'check_out' => '2026-09-01 22:00:00+00',
            'status' => 'ontime',
            'late_minutes' => 0,
            'hours_worked' => 6.0,
            'method' => 'mobile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}/presence?date=2026-09-01")
            ->assertStatus(200)
            ->assertJsonPath('data.presence.0.status', 'present')
            ->assertJsonPath('data.presence.0.outside_shift', true);
    }

    public function test_operator_self_service_presence(): void
    {
        $company = $this->makeCompanyWithTimezone();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        FuelShiftAssignment::query()->create([
            'company_id' => $company->id,
            'shift_id' => $shift->id,
            'employee_id' => $operator->id,
            'assignment_date' => '2026-09-01',
        ]);

        DB::table('attendance_logs')->insert([
            'company_id' => $company->id,
            'employee_id' => $operator->id,
            'date' => '2026-09-01',
            'check_in' => '2026-09-01 06:05:00+00',
            'check_out' => '2026-09-01 14:00:00+00',
            'status' => 'ontime',
            'late_minutes' => 5,
            'hours_worked' => 7.9,
            'method' => 'mobile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/fuel-station/me/presence?date=2026-09-01')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.presence')
            ->assertJsonPath('data.presence.0.employee_id', $operator->id)
            ->assertJsonPath('data.presence.0.status', 'late')
            ->assertJsonPath('data.presence.0.shift.name', 'Matin');
    }

    public function test_cross_tenant_shift_roster_is_404(): void
    {
        $companyA = $this->makeCompanyWithTimezone();
        $companyB = $this->makeCompanyWithTimezone();
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($managerB);

        $shift = FuelShift::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ]);

        $this->getJson("/api/v1/fuel-station/shifts/{$shift->id}/presence?date=2026-09-01")->assertStatus(404);
    }
}
