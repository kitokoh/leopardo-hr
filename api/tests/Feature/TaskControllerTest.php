<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Planning\Domain\Models\Task;
use App\Modules\Planning\Domain\Models\TaskComment;
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

    public function test_assigned_employee_can_post_and_list_task_comments(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $task = Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Verification materiel',
            'created_by' => $manager->id,
            'assigned_to' => [$employee->id],
            'due_date' => now('UTC')->toDateString(),
            'priority' => 'normal',
            'estimated_minutes' => 30,
            'status' => 'todo',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/tasks/{$task->id}/comments", [
            'content' => 'Materiel verifie, tout est conforme.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Materiel verifie, tout est conforme.')
            ->assertJsonPath('data.author_id', $employee->id);

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Materiel verifie, tout est conforme.');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'author_id' => $employee->id,
            'content' => 'Materiel verifie, tout est conforme.',
        ]);

        // The manager (task creator) should receive an in-app notification,
        // but the comment author should not notify themselves.
        $this->assertSame(1, Notification::query()->where('employee_id', $manager->id)->count());
        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());
    }

    public function test_comment_author_is_not_notified_and_unrelated_employee_cannot_access_comments(): void
    {
        $company = Company::factory()->create(['timezone' => 'UTC']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $outsider = Employee::factory()->create(['company_id' => $company->id]);
        $task = Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Audit securite',
            'created_by' => $manager->id,
            'assigned_to' => [],
            'due_date' => now('UTC')->toDateString(),
            'priority' => 'normal',
            'estimated_minutes' => 30,
            'status' => 'todo',
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/tasks/{$task->id}/comments")->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['content' => 'Tentative non autorisee.'])->assertForbidden();

        $this->assertSame(0, TaskComment::query()->count());
    }
}

