<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Policies\ExpenseClaimPolicy;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-022 (issue #8146) — zone 4/5 : scoping manager `dept` (département)
 * et `superviseur` (subordonnés directs), FAIL-CLOSED.
 *
 * Les primitives de scoping (App\Core\Auth\Domain\Models\Employee :
 * managesDepartmentOf / managesEmployeeDirectly / managesTeamMemberOf /
 * scopeVisibleToManager) sont les briques canoniques PA2-SEC-002/003
 * consommées par les surfaces RH/IA/notifications autour du Planning.
 *
 * Fail-closed vérifié : un manager `dept` SANS département assigné ne gère
 * personne (pas de repli « voit tout ») ; un superviseur ne voit que ses
 * subordonnés directs (manager_id) et lui-même.
 */
class ManagerScopeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::factory()->create(['company_id' => $this->company->id, ...$attributes]);
    }

    public function test_dept_manager_manages_employee_in_own_department(): void
    {
        $dept = Department::create(['name' => 'Production']);
        $manager = $this->makeEmployee([
            'role' => 'manager', 'manager_role' => 'dept', 'department_id' => $dept->id,
        ]);
        $agent = $this->makeEmployee(['department_id' => $dept->id]);

        $this->assertTrue($manager->managesDepartmentOf($agent));
        $this->assertTrue($manager->managesTeamMemberOf($agent));
    }

    public function test_dept_manager_does_not_manage_employee_in_other_department(): void
    {
        $deptA = Department::create(['name' => 'Production']);
        $deptB = Department::create(['name' => 'Logistique']);
        $manager = $this->makeEmployee([
            'role' => 'manager', 'manager_role' => 'dept', 'department_id' => $deptA->id,
        ]);
        $outsider = $this->makeEmployee(['department_id' => $deptB->id]);

        $this->assertFalse($manager->managesDepartmentOf($outsider));
        $this->assertFalse($manager->managesTeamMemberOf($outsider));
    }

    public function test_dept_manager_without_department_is_fail_closed(): void
    {
        $dept = Department::create(['name' => 'Production']);
        // Manager `dept` mal configuré : aucun département assigné.
        $manager = $this->makeEmployee([
            'role' => 'manager', 'manager_role' => 'dept', 'department_id' => null,
        ]);
        $agent = $this->makeEmployee(['department_id' => $dept->id]);

        // Fail-closed : il ne gère PERSONNE plutôt que tout le monde.
        $this->assertFalse($manager->managesDepartmentOf($agent));
        $this->assertFalse($manager->managesTeamMemberOf($agent));
    }

    public function test_superviseur_manages_direct_report(): void
    {
        $supervisor = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'superviseur']);
        $report = $this->makeEmployee(['manager_id' => $supervisor->id]);

        $this->assertTrue($supervisor->managesEmployeeDirectly($report));
        $this->assertTrue($supervisor->managesTeamMemberOf($report));
    }

    public function test_superviseur_does_not_manage_non_report(): void
    {
        $supervisor = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'superviseur']);
        $otherManager = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'principal']);
        $notHisTeam = $this->makeEmployee(['manager_id' => $otherManager->id]);

        $this->assertFalse($supervisor->managesEmployeeDirectly($notHisTeam));
        $this->assertFalse($supervisor->managesTeamMemberOf($notHisTeam));
    }

    public function test_superviseur_manages_self(): void
    {
        $supervisor = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'superviseur']);

        // Soi-même est toujours inclus : un superviseur agit sur ses propres
        // enregistrements (pointages, demandes).
        $this->assertTrue($supervisor->managesEmployeeDirectly($supervisor));
    }

    public function test_visible_to_manager_scopes_query_to_own_department(): void
    {
        $deptA = Department::create(['name' => 'Production']);
        $deptB = Department::create(['name' => 'Logistique']);
        $manager = $this->makeEmployee([
            'role' => 'manager', 'manager_role' => 'dept', 'department_id' => $deptA->id,
        ]);
        $inDept = $this->makeEmployee(['department_id' => $deptA->id]);
        $outDept = $this->makeEmployee(['department_id' => $deptB->id]);

        $visibleIds = Employee::query()
            ->where('company_id', $this->company->id)
            ->tap(fn ($q) => $manager->scopeVisibleToManager($q, $manager))
            ->pluck('id');

        $this->assertTrue($visibleIds->contains($manager->id)); // lui-même (même dept non requis ici, mais department_id posé)
        $this->assertTrue($visibleIds->contains($inDept->id));
        $this->assertFalse($visibleIds->contains($outDept->id));
    }

    public function test_visible_to_manager_scopes_query_to_direct_reports_and_self(): void
    {
        $supervisor = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'superviseur']);
        $report = $this->makeEmployee(['manager_id' => $supervisor->id]);
        $outsider = $this->makeEmployee();

        $visibleIds = Employee::query()
            ->where('company_id', $this->company->id)
            ->tap(fn ($q) => $supervisor->scopeVisibleToManager($q, $supervisor))
            ->pluck('id');

        $this->assertTrue($visibleIds->contains($supervisor->id));
        $this->assertTrue($visibleIds->contains($report->id));
        $this->assertFalse($visibleIds->contains($outsider->id));
    }

    public function test_dept_manager_without_department_sees_nobody_in_query(): void
    {
        $dept = Department::create(['name' => 'Production']);
        $manager = $this->makeEmployee([
            'role' => 'manager', 'manager_role' => 'dept', 'department_id' => null,
        ]);
        $agent = $this->makeEmployee(['department_id' => $dept->id]);

        // Fail-closed au niveau SQL : department_id = -1 ne matche personne.
        $visibleIds = Employee::query()
            ->where('company_id', $this->company->id)
            ->tap(fn ($q) => $manager->scopeVisibleToManager($q, $manager))
            ->pluck('id');

        $this->assertFalse($visibleIds->contains($agent->id));
        $this->assertFalse($visibleIds->contains($manager->id));
    }

    /**
     * La policy enregistrée (Gate::policy) restreint l'approbation des notes
     * de frais aux rôles company-wide : principal / comptable / rh. Les rôles
     * team-scoped (dept, superviseur) en sont exclus — règle fail-closed de
     * la policy, testée telle qu'elle est déclarée.
     */
    public function test_expense_claim_policy_approval_is_fail_closed_for_team_scoped_roles(): void
    {
        $policy = new ExpenseClaimPolicy;
        $claim = new ExpenseClaim(['company_id' => $this->company->id]);

        $principal = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'principal']);
        $rh = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'rh']);
        $comptable = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'comptable']);
        $dept = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'dept']);
        $superviseur = $this->makeEmployee(['role' => 'manager', 'manager_role' => 'superviseur']);

        $this->assertTrue($policy->approve($principal, $claim));
        $this->assertTrue($policy->approve($rh, $claim));
        $this->assertTrue($policy->approve($comptable, $claim));
        $this->assertFalse($policy->approve($dept, $claim));
        $this->assertFalse($policy->approve($superviseur, $claim));
    }
}
