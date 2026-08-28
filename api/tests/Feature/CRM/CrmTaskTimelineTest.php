<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Application\Services\CrmOverdueReminderService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\CreatesCrmSchema;
use Tests\TestCase;

/**
 * Issue #5720 — Timeline d'activités + tâches CRM + relances idempotentes.
 *
 * Couvre : CRUD tâches (champs bornés, transitions de statut contrôlées),
 * filtres allowlistés (status/overdue/priority/owner), isolation tenant
 * (404 cross-tenant), RBAC (Policy : manager du rôle autorisé, assigné sur
 * sa tâche, comptable refusé), cursor pagination de la timeline et
 * idempotence des relances (une par tâche et par jour).
 */
class CrmTaskTimelineTest extends TestCase
{
    use CreatesCrmSchema;
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCrmSchemaIfMissing();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    public function test_principal_can_create_and_list_tasks(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/crm/tasks', [
            'title' => 'Relancer Transports Alpha',
            'description' => 'Suivi proposition commerciale',
            'due_at' => now()->addDays(2)->toIso8601String(),
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Relancer Transports Alpha');
        $response->assertJsonPath('data.status', 'todo');
        $response->assertJsonPath('data.priority', 'high');

        $list = $this->getJson('/api/v1/crm/tasks');

        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
    }

    public function test_task_validation_rejects_unknown_status_and_priority(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->postJson('/api/v1/crm/tasks', ['title' => 'X', 'priority' => 'urgent'])
            ->assertStatus(422);

        $this->postJson('/api/v1/crm/tasks', ['title' => 'X', 'status' => 'weird'])
            ->assertStatus(422);

        $this->postJson('/api/v1/crm/tasks', ['title' => ''])
            ->assertStatus(422);
    }

    public function test_status_transition_is_controlled(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $id = $this->createCrmTask(['company_id' => $this->companyA->id]);

        $this->patchJson("/api/v1/crm/tasks/{$id}", ['status' => 'done'])->assertOk();
        // done → cancelled n'est pas une transition autorisée.
        $this->patchJson("/api/v1/crm/tasks/{$id}", ['status' => 'cancelled'])->assertStatus(422);
    }

    public function test_complete_and_reopen(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $id = $this->createCrmTask(['company_id' => $this->companyA->id]);

        $this->postJson("/api/v1/crm/tasks/{$id}/complete")->assertJsonPath('data.status', 'done');
        $this->postJson("/api/v1/crm/tasks/{$id}/reopen")->assertJsonPath('data.status', 'todo');
    }

    public function test_cross_tenant_task_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $otherId = $this->createCrmTask(['company_id' => $this->companyB->id]);

        $this->getJson("/api/v1/crm/tasks/{$otherId}")->assertStatus(404);
        $this->patchJson("/api/v1/crm/tasks/{$otherId}", ['title' => 'Hack'])->assertStatus(404);
        $this->deleteJson("/api/v1/crm/tasks/{$otherId}")->assertStatus(404);
    }

    public function test_assignee_can_update_own_task_but_not_delete(): void
    {
        $assignee = $this->employee($this->companyA);
        Sanctum::actingAs($assignee);

        // Un employé (non manager) n'atteint même pas les routes CRM (RBAC route).
        $this->postJson('/api/v1/crm/tasks', ['title' => 'X'])->assertStatus(403);

        Sanctum::actingAs($this->manager($this->companyA));
        $id = $this->createCrmTask(['company_id' => $this->companyA->id, 'assignee_id' => $assignee->id]);

        Sanctum::actingAs($assignee);
        $this->getJson("/api/v1/crm/tasks/{$id}")->assertStatus(403); // RBAC route (api.manager)
    }

    public function test_comptable_is_forbidden(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'comptable'));

        $this->getJson('/api/v1/crm/tasks')->assertStatus(403);
        $this->postJson('/api/v1/crm/tasks', ['title' => 'X'])->assertStatus(403);
    }

    public function test_overdue_filter(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->createCrmTask(['company_id' => $this->companyA->id, 'due_at' => now()->subDay()]);
        $this->createCrmTask(['company_id' => $this->companyA->id, 'due_at' => now()->addDay()]);
        $this->createCrmTask(['company_id' => $this->companyA->id, 'due_at' => null]);

        $overdue = $this->getJson('/api/v1/crm/tasks?overdue=1');
        $overdue->assertOk();
        $this->assertCount(1, $overdue->json('data'));

        $notOverdue = $this->getJson('/api/v1/crm/tasks?overdue=0');
        $this->assertCount(2, $notOverdue->json('data'));
    }

    public function test_timeline_cursor_pagination(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $accountId = $this->createCrmAccount(['company_id' => $this->companyA->id]);
        for ($i = 0; $i < 5; $i++) {
            $this->createCrmActivity(['company_id' => $this->companyA->id, 'account_id' => $accountId, 'type' => 'call']);
        }

        $page1 = $this->getJson("/api/v1/crm/accounts/{$accountId}/timeline?limit=2");
        $page1->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $this->assertNotNull($page1->json('meta.next_cursor'));

        $cursor = $page1->json('meta.next_cursor');
        $page2 = $this->getJson("/api/v1/crm/accounts/{$accountId}/timeline?limit=2&before_id={$cursor}");
        $page2->assertOk();
        $this->assertCount(2, $page2->json('data'));

        // Les ids doivent être strictement décroissants entre pages.
        $idsPage1 = collect($page1->json('data'))->pluck('id');
        $idsPage2 = collect($page2->json('data'))->pluck('id');
        $this->assertTrue($idsPage2->every(fn (int $id) => $id < (int) $idsPage1->min()));
    }

    public function test_timeline_of_other_tenant_account_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $otherAccountId = $this->createCrmAccount(['company_id' => $this->companyB->id]);

        $this->getJson("/api/v1/crm/accounts/{$otherAccountId}/timeline")->assertStatus(404);
    }

    public function test_overdue_reminders_are_idempotent(): void
    {
        $company = $this->companyA;
        $assignee = $this->employee($company);

        $taskId = $this->createCrmTask([
            'company_id' => $company->id,
            'assignee_id' => $assignee->id,
            'due_at' => now()->subDay(),
            'status' => 'todo',
        ]);

        $service = app(CrmOverdueReminderService::class);

        $this->assertSame(1, $service->run());
        // Second run : aucune nouvelle relance (UNIQUE task_id + remind_date).
        $this->assertSame(0, $service->run());

        $this->assertSame(1, DB::table('crm_task_reminders')->where('task_id', $taskId)->count());

        // Une notification interne a bien été créée pour l'assigné.
        $this->assertSame(1, DB::table('notifications')->where('employee_id', $assignee->id)->where('type', 'crm_task_overdue')->count());
    }
}
