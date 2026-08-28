<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5710 — timeline (activities) + tâches + ownership CRM client.
 *
 * Vérifie : timeline append-only avec types allowlistés (CHECK), tâches V0
 * alignées main (`assignee_id` + `done`), ownership et scoping tenant,
 * indexes temporels, et audit automatique des mutations (Auditable).
 */
class CrmActivitiesTasksTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_activity_type_check_rejects_unknown_type(): void
    {
        $company = $this->company('DZ');

        $this->expectException(QueryException::class);

        DB::table('crm_activities')->insert([
            'company_id' => $company->id,
            'type' => 'ping',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_activity_append_with_allowed_type(): void
    {
        $company = $this->company('DZ');

        $activity = CrmActivity::query()->create([
            'company_id' => $company->id,
            'type' => CrmActivity::TYPE_CALL,
            'subject' => 'Appel de qualification',
            'occurred_at' => now(),
        ]);

        $this->assertSame(CrmActivity::TYPE_CALL, $activity->type);
        $this->assertDatabaseHas('crm_activities', [
            'id' => $activity->id,
            'company_id' => $company->id,
            'type' => CrmActivity::TYPE_CALL,
        ]);
    }

    public function test_task_create_with_assignee_and_done_default(): void
    {
        $company = $this->company('DZ');

        $task = CrmTask::query()->create([
            'company_id' => $company->id,
            'title' => 'Relancer Sarah',
            'assignee_id' => 7,
        ]);
        $task->refresh();

        $this->assertSame(7, $task->assignee_id);
        $this->assertFalse($task->done);
    }

    public function test_task_mark_done_and_audit(): void
    {
        $company = $this->company('DZ');

        $task = CrmTask::query()->create([
            'company_id' => $company->id,
            'title' => 'Envoyer la proposition',
            'assignee_id' => 7,
        ]);

        $task->markAsDone();

        $this->assertTrue($task->done);

        // Audit automatique des mutations (Auditable) : création + mise à jour.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'created',
            'auditable_type' => CrmTask::class,
            'auditable_id' => $task->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'updated',
            'auditable_type' => CrmTask::class,
            'auditable_id' => $task->id,
        ]);
    }

    public function test_activity_creation_is_audited(): void
    {
        $company = $this->company('DZ');

        $activity = CrmActivity::query()->create([
            'company_id' => $company->id,
            'type' => CrmActivity::TYPE_NOTE,
            'subject' => 'Note de cadrage',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'created',
            'auditable_type' => CrmActivity::class,
            'auditable_id' => $activity->id,
        ]);
    }

    public function test_task_overdue_detection(): void
    {
        $company = $this->company('DZ');

        $overdue = CrmTask::query()->create([
            'company_id' => $company->id,
            'title' => 'En retard',
            'due_at' => now()->subDay(),
        ]);
        $future = CrmTask::query()->create([
            'company_id' => $company->id,
            'title' => 'À venir',
            'due_at' => now()->addDay(),
        ]);
        $done = CrmTask::query()->create([
            'company_id' => $company->id,
            'title' => 'Terminée en retard',
            'due_at' => now()->subDay(),
            'done' => true,
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($future->isOverdue());
        $this->assertFalse($done->isOverdue());
    }

    public function test_activity_is_invisible_from_another_tenant(): void
    {
        $companyA = $this->company('DZ');
        $companyB = $this->company('MA');

        $activity = CrmActivity::query()->create([
            'company_id' => $companyA->id,
            'type' => CrmActivity::TYPE_EMAIL,
        ]);

        app()->instance('current_company', $companyB);

        $this->assertNull(
            CrmActivity::query()->whereKey($activity->id)->first(),
            'L’activité d’un autre tenant ne doit pas être visible.'
        );
    }

    public function test_task_ownership_scoped_to_tenant(): void
    {
        $companyA = $this->company('DZ');
        $companyB = $this->company('MA');

        $task = CrmTask::query()->create([
            'company_id' => $companyA->id,
            'title' => 'Tâche du tenant A',
            'assignee_id' => 42,
        ]);

        // Depuis le tenant B, même assignee_id, la tâche reste invisible.
        app()->instance('current_company', $companyB);

        $this->assertNull(
            CrmTask::query()->where('assignee_id', 42)->first(),
            'La tâche d’un autre tenant ne doit pas fuiter par ownership.'
        );
    }

    public function test_temporal_indexes_exist(): void
    {
        foreach ([
            'crm_activities' => ['crm_activities_company_occurred_idx', 'crm_activities_company_account_occurred_idx'],
            'crm_tasks' => ['crm_tasks_company_due_idx', 'crm_tasks_company_done_idx'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->assertTrue(Schema::hasIndex($table, $index), "Index {$index} manquant sur {$table}.");
            }
        }
    }

    private function company(string $country): Company
    {
        $currency = $country === 'DZ' ? 'DZD' : 'MAD';

        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        return $company;
    }
}
