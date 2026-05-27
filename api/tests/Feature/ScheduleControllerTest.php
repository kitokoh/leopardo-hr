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
            'work_days' => [1, 2, 3, 4, 5, 6],
            'late_tolerance_minutes' => 5,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 44,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Equipe matin')
            ->assertJsonPath('data.company_id', $company->id)
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
}
