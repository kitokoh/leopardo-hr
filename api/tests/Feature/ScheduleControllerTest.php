<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
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

    public function test_manager_can_create_and_list_company_schedules_only(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Schedule::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Horaire autre tenant',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/schedules', [
            'name' => 'Equipe matin',
            'start_time' => '07:30',
            'end_time' => '15:30',
            'break_minutes' => 30,
            'break_rules' => [
                ['label' => 'Pause midi', 'start_time' => '12:00', 'end_time' => '12:30', 'minutes' => 30, 'is_paid' => false],
            ],
            'work_days' => [1, 2, 3, 4, 5, 6],
            'rest_days' => [7],
            'leave_rules' => [
                ['label' => 'Conge annuel', 'type' => 'annual', 'days_per_year' => 21],
            ],
            'assignment_notes' => 'Equipe terrain avec repos dimanche.',
            'late_tolerance_minutes' => 5,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 44,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Equipe matin')
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.rest_days.0', 7)
            ->assertJsonPath('data.break_rules.0.label', 'Pause midi')
            ->assertJsonPath('data.leave_rules.0.type', 'annual')
            ->assertJsonPath('data.assignment_notes', 'Equipe terrain avec repos dimanche.')
            ->assertJsonPath('data.is_default', true);

        $this->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Equipe matin');
    }

    public function test_employee_cannot_manage_schedules(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/schedules')->assertForbidden();
        $this->postJson('/api/v1/schedules', [
            'name' => 'Non autorise',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertForbidden();
    }

    public function test_manager_can_assign_company_schedule_when_creating_employee(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Equipe terrain',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_minutes' => 20,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 5,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Karim',
            'last_name' => 'Terrain',
            'email' => 'karim.terrain@example.test',
            'password' => 'password123',
            'schedule_id' => $schedule->id,
            'role' => 'employee',
        ])
            ->assertCreated()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.schedule.name', 'Equipe terrain');
    }

    public function test_manager_cannot_assign_schedule_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $foreignSchedule = Schedule::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Autre tenant',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Ahmet',
            'last_name' => 'Wrong',
            'email' => 'ahmet.wrong@example.test',
            'password' => 'password123',
            'schedule_id' => $foreignSchedule->id,
            'role' => 'employee',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schedule_id']);
    }

    public function test_manager_can_update_employee_schedule_salary_and_position(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Sara',
            'last_name' => 'Ops',
            'salary_type' => 'fixed',
            'salary_base' => 45000,
        ]);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Equipe soir',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'break_minutes' => 30,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 5,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/employees/{$employee->id}", [
            'schedule_id' => $schedule->id,
            'salary_type' => 'hourly',
            'hourly_rate' => 650,
            'contract_start' => '2026-05-01',
            'extra_data' => [
                'department' => 'Operations',
                'job_title' => 'Cheffe equipe',
                'work_location' => 'Site Est',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.schedule.name', 'Equipe soir')
            ->assertJsonPath('data.salary_type', 'hourly')
            ->assertJsonPath('data.hourly_rate', 650)
            ->assertJsonPath('data.hire_date', '2026-05-01')
            ->assertJsonPath('data.extra_data.department', 'Operations')
            ->assertJsonPath('data.extra_data.job_title', 'Cheffe equipe')
            ->assertJsonPath('data.extra_data.work_location', 'Site Est');
    }

    public function test_manager_can_assign_schedule_to_existing_company_employees(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $first = Employee::factory()->create(['company_id' => $company->id]);
        $second = Employee::factory()->create(['company_id' => $company->id]);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Regles atelier',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 45,
            'work_days' => [1, 2, 3, 4, 5],
            'rest_days' => [6, 7],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/schedules/{$schedule->id}/assign-employees", [
            'employee_ids' => [$first->id, $second->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_count', 2)
            ->assertJsonPath('data.schedule.name', 'Regles atelier');

        $this->assertDatabaseHas('employees', [
            'id' => $first->id,
            'schedule_id' => $schedule->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $second->id,
            'schedule_id' => $schedule->id,
        ]);
    }

    public function test_manager_cannot_assign_schedule_to_foreign_employee(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Regles internes',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/schedules/{$schedule->id}/assign-employees", [
            'employee_ids' => [$employee->id, $foreignEmployee->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_ids']);

        $this->assertDatabaseMissing('employees', [
            'id' => $employee->id,
            'schedule_id' => $schedule->id,
        ]);
        $this->assertDatabaseMissing('employees', [
            'id' => $foreignEmployee->id,
            'schedule_id' => $schedule->id,
        ]);
    }
}
