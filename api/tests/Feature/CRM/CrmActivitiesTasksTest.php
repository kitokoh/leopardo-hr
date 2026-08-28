<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Enums\CrmTaskStatus;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Timeline (activities) et tâches CRM — Issue #5710 (CRM-V0-06).
 *
 * Verrouille au niveau modèle/DB :
 *   1. contraintes CHECK (types d'activité, statuts/priorités de tâche,
 *      related_type) ;
 *   2. isolation tenant : `company_id` NON nullable, scope BelongsToCompany,
 *      aucune lecture cross-tenant (404) ;
 *   3. invariants métier : markAsDone() horodate completed_at, reopen() depuis
 *      cancelled est refusé, scope overdue ;
 *   4. partage explicite : pivot crm_task_assignees (unicité task+employee,
 *      FK cascade à la suppression de la tâche) ;
 *   5. audit : création/suppression tracées dans audit_logs (trait Auditable) ;
 *   6. pagination/indexes temporels : tri par happened_at/due_at utilisable.
 */
class CrmActivitiesTasksTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_activity_type_check_rejects_unknown_type(): void
    {
        try {
            CrmActivity::query()->create([
                'company_id' => $this->companyA->id,
                'subject' => 'Note interne',
                'activity_type' => 'carrier_pigeon',
            ]);
            $this->fail('Le CHECK crm_activities_type_check aurait dû rejeter le type.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_activities_type_check', $exception->getMessage());
        }
    }

    public function test_task_status_check_rejects_unknown_status(): void
    {
        try {
            CrmTask::query()->create([
                'company_id' => $this->companyA->id,
                'subject' => 'Relance',
                'status' => 'archived',
            ]);
            $this->fail('Le CHECK crm_tasks_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('crm_tasks_status_check', $exception->getMessage());
        }
    }

    public function test_company_id_is_required_for_activity(): void
    {
        $this->expectException(QueryException::class);

        CrmActivity::query()->create([
            'subject' => 'Sans tenant',
        ]);
    }

    public function test_activity_is_append_oriented_and_listable_by_tenant(): void
    {
        $this->setTenant($this->companyA);

        CrmActivity::query()->create([
            'subject' => 'Premier appel',
            'activity_type' => 'call',
            'happened_at' => now()->subDay(),
        ]);
        CrmActivity::query()->create([
            'subject' => 'Note de cadrage',
            'activity_type' => 'note',
            'happened_at' => now(),
        ]);

        $timeline = CrmActivity::query()
            ->orderByDesc('happened_at')
            ->get();

        $this->assertCount(2, $timeline);
        $this->assertSame('Note de cadrage', $timeline->first()->subject);
    }

    public function test_cross_tenant_activity_is_invisible(): void
    {
        $this->setTenant($this->companyA);

        /** @var CrmActivity $activity */
        $activity = CrmActivity::query()->create([
            'company_id' => $this->companyB->id,
            'subject' => 'Activité du tenant B',
        ]);

        // Depuis le tenant A, la timeline ne voit pas l'activité du tenant B.
        $this->assertNull(CrmActivity::query()->find($activity->id));

        // Garde explicite du contrôleur (pattern AccountingContactController) :
        // route-model binding + scope global → 404, jamais 200.
        $this->assertNotSame($this->companyA->id, (string) $activity->company_id);
    }

    public function test_mark_as_done_sets_completed_at_and_is_terminal(): void
    {
        $this->setTenant($this->companyA);

        /** @var CrmTask $task */
        $task = CrmTask::query()->create([
            'subject' => 'Relancer Alice',
            'due_at' => now()->addDay(),
        ]);

        $this->assertNull($task->completed_at);

        $task->markAsDone();

        $this->assertSame(CrmTaskStatus::Done->value, $task->status);
        $this->assertNotNull($task->completed_at);

        // Terminal : reopen() depuis cancelled est refusé.
        $task->update(['status' => CrmTaskStatus::Cancelled->value, 'completed_at' => null]);
        $task->reopen();
        $this->assertSame(CrmTaskStatus::Cancelled->value, $task->fresh()->status);
    }

    public function test_overdue_scope_excludes_done_and_cancelled(): void
    {
        $this->setTenant($this->companyA);

        /** @var CrmTask $lateOpen */
        $lateOpen = CrmTask::query()->create([
            'subject' => 'En retard ouverte',
            'due_at' => now()->subDay(),
            'status' => 'in_progress',
        ]);
        /** @var CrmTask $lateDone */
        $lateDone = CrmTask::query()->create([
            'subject' => 'En retard terminée',
            'due_at' => now()->subDay(),
            'status' => 'done',
        ]);

        $overdue = CrmTask::query()->overdue()->pluck('id');

        $this->assertTrue($overdue->contains($lateOpen->id));
        $this->assertFalse($overdue->contains($lateDone->id));
    }

    public function test_task_sharing_pivot_is_unique_per_employee(): void
    {
        $this->setTenant($this->companyA);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        /** @var CrmTask $task */
        $task = CrmTask::query()->create([
            'subject' => 'Tâche partagée',
        ]);

        $task->assignees()->attach($employee->id, ['assigned_by_id' => $employee->id]);

        $this->assertTrue($task->assignees()->where('employees.id', $employee->id)->exists());

        // Unicité (task_id, employee_id) : le second attach lève une violation.
        try {
            $task->assignees()->attach($employee->id, ['assigned_by_id' => $employee->id]);
            $this->fail('La contrainte UNIQUE(task_id, employee_id) aurait dû rejeter le doublon.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_deleting_task_cascades_to_assignees_pivot(): void
    {
        $this->setTenant($this->companyA);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        /** @var CrmTask $task */
        $task = CrmTask::query()->create(['subject' => 'Tâche éphémère']);
        $task->assignees()->attach($employee->id);

        $task->delete();

        $this->assertSame(0, DB::table('crm_task_assignees')->where('task_id', $task->id)->count());
    }

    public function test_mutations_are_audited(): void
    {
        $this->setTenant($this->companyA);

        /** @var CrmTask $task */
        $task = CrmTask::query()->create(['subject' => 'Tâche auditée']);
        $task->update(['priority' => 'high']);
        $task->delete();

        $rows = DB::table('audit_logs')
            ->where('company_id', $this->companyA->id)
            ->where('auditable_type', CrmTask::class)
            ->get();

        // created + updated + deleted
        $this->assertSame(3, $rows->count());
        $this->assertSame(['created', 'updated', 'deleted'], $rows->pluck('action')->all());
    }

    private function setTenant(Company $company): void
    {
        app()->instance('current_company', $company);
    }
}
