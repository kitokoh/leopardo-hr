<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TaskControllerTest extends TestCase
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

    public function test_manager_cannot_assign_task_to_employee_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/tasks', [
            'title' => 'Controle site',
            'assigned_to' => [$foreignEmployee->id],
            'due_date' => now()->toDateString(),
            'estimated_minutes' => 60,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to.0']);
    }

    public function test_employee_can_complete_own_today_task_with_performance_score(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $task = Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Tour terrain',
            'created_by' => $employee->id,
            'assigned_to' => [$employee->id],
            'due_date' => now('UTC')->toDateString(),
            'priority' => 'normal',
            'estimated_minutes' => 60,
            'status' => 'todo',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/tasks/today')
            ->assertOk()
            ->assertJsonPath('data.0.id', $task->id);

        $this->patchJson("/api/v1/tasks/{$task->id}", [
            'status' => 'done',
            'completed_minutes' => 45,
            'completion_note' => 'Termine sans anomalie.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_minutes', 45)
            ->assertJsonPath('data.completion_note', 'Termine sans anomalie.')
            ->assertJsonPath('data.performance_score', '66.67');
    }

    public function test_assigned_employee_cannot_reassign_task_when_completing_it(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $colleague = Employee::factory()->create(['company_id' => $company->id]);
        $task = Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Controle stock',
            'created_by' => $colleague->id,
            'assigned_to' => [$employee->id],
            'due_date' => now('UTC')->toDateString(),
            'priority' => 'normal',
            'estimated_minutes' => 30,
            'status' => 'todo',
        ]);

        Sanctum::actingAs($employee);

        $this->patchJson("/api/v1/tasks/{$task->id}", [
            'status' => 'done',
            'completed_minutes' => 25,
            'assigned_to' => [$colleague->id],
            'title' => 'Titre modifie',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.assigned_to.0', $employee->id)
            ->assertJsonPath('data.title', 'Controle stock');
    }
}
