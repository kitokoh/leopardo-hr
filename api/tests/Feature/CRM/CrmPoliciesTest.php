<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Domain\Models\CrmPipeline;
use App\Modules\CRM\Domain\Models\CrmPipelineStage;
use App\Modules\CRM\Domain\Models\CrmTask;
use Illuminate\Support\Facades\Gate;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5711 — Policies CRM client : permissions par rôle ET isolation
 * tenant (403/404). Chaque politique est vérifiée directement via le Gate
 * (mêmes chemins que `$this->authorize()` des controllers).
 */
class CrmPoliciesTest extends TestCase
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

    // ---------------------------------------------------------------------
    // Rôles : managers (principal / rh / marketing) vs employé
    // ---------------------------------------------------------------------

    public function test_principal_manager_can_manage_all_crm_resources(): void
    {
        $manager = $this->manager('principal', $this->companyA->id);

        foreach ([
            ['viewAny', CrmPipeline::class],
            ['create', CrmPipeline::class],
            ['viewAny', CrmPipelineStage::class],
            ['create', CrmPipelineStage::class],
            ['viewAny', CrmLead::class],
            ['create', CrmLead::class],
            ['viewAny', CrmOpportunity::class],
            ['create', CrmOpportunity::class],
            ['viewAny', CrmActivity::class],
            ['create', CrmActivity::class],
            ['viewAny', CrmTask::class],
            ['create', CrmTask::class],
        ] as [$ability, $class]) {
            $this->assertTrue(Gate::forUser($manager)->allows($ability, $class), "$ability $class refusé au principal.");
        }
    }

    public function test_rh_manager_can_manage_all_crm_resources(): void
    {
        $manager = $this->manager('rh', $this->companyA->id);

        $this->assertTrue(Gate::forUser($manager)->allows('create', CrmLead::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', CrmOpportunity::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', CrmTask::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', CrmPipeline::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', CrmActivity::class));
    }

    public function test_plain_employee_cannot_create_or_list_crm_resources(): void
    {
        $employee = $this->employee($this->companyA->id);

        foreach ([CrmPipeline::class, CrmLead::class, CrmOpportunity::class, CrmActivity::class, CrmTask::class] as $class) {
            $this->assertFalse(Gate::forUser($employee)->allows('viewAny', $class), "viewAny $class autorisé à un employé.");
            $this->assertFalse(Gate::forUser($employee)->allows('create', $class), "create $class autorisé à un employé.");
        }
    }

    // ---------------------------------------------------------------------
    // Ownership : lead visible par son owner, tâche mutable par son assigné
    // ---------------------------------------------------------------------

    public function test_employee_can_view_only_own_lead(): void
    {
        $manager = $this->manager('principal', $this->companyA->id);
        $owner = $this->employee($this->companyA->id);
        $other = $this->employee($this->companyA->id);

        $ownedLead = CrmLead::query()->create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'owner_id' => $owner->id,
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $ownedLead));
        $this->assertFalse(Gate::forUser($other)->allows('view', $ownedLead));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $ownedLead));
    }

    public function test_assignee_can_update_but_not_delete_own_task(): void
    {
        $manager = $this->manager('principal', $this->companyA->id);
        $assignee = $this->employee($this->companyA->id);

        $task = CrmTask::query()->create([
            'company_id' => $this->companyA->id,
            'title' => 'Relancer le client',
            'assigned_to' => $assignee->id,
        ]);

        $this->assertTrue(Gate::forUser($assignee)->allows('view', $task));
        $this->assertTrue(Gate::forUser($assignee)->allows('update', $task));
        $this->assertFalse(Gate::forUser($assignee)->allows('delete', $task));
        $this->assertTrue(Gate::forUser($manager)->allows('delete', $task));
    }

    // ---------------------------------------------------------------------
    // Isolation tenant : jamais d'accès cross-tenant, même manager
    // ---------------------------------------------------------------------

    public function test_manager_cannot_view_resource_of_another_company(): void
    {
        $managerB = $this->manager('principal', $this->companyB->id);

        $pipelineA = CrmPipeline::query()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Ventes A',
        ]);
        $leadA = CrmLead::query()->create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $taskA = CrmTask::query()->create([
            'company_id' => $this->companyA->id,
            'title' => 'Tâche A',
        ]);

        $this->assertFalse(Gate::forUser($managerB)->allows('view', $pipelineA));
        $this->assertFalse(Gate::forUser($managerB)->allows('view', $leadA));
        $this->assertFalse(Gate::forUser($managerB)->allows('view', $taskA));
        $this->assertFalse(Gate::forUser($managerB)->allows('update', $pipelineA));
    }

    public function test_stage_policy_enforces_same_company(): void
    {
        $managerB = $this->manager('principal', $this->companyB->id);

        $stageA = CrmPipelineStage::query()->create([
            'company_id' => $this->companyA->id,
            'pipeline_id' => CrmPipeline::query()->create([
                'company_id' => $this->companyA->id,
                'name' => 'Pipeline A',
            ])->id,
            'name' => 'Stage A',
            'position' => 0,
        ]);

        $this->assertFalse(Gate::forUser($managerB)->allows('view', $stageA));
        $this->assertFalse(Gate::forUser($managerB)->allows('update', $stageA));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function manager(string $role, string $companyId): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $companyId,
            'role' => 'manager',
            'manager_role' => $role,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function employee(string $companyId): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $companyId,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }
}
