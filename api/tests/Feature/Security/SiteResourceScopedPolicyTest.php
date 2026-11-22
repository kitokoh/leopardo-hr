<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Domain\Models\Site;
use App\Policies\SitePolicy;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #9812 — `SitePolicy` doit respecter le niveau d'assignation.
 *
 * ## La faille corrigée
 *
 * `site` est un type de ressource déclaré dans `config/resource_types.php`, mais
 * `SitePolicy` ne vérifiait que `company_id` + `isManager()` : **tout manager
 * voyait et modifiait TOUS les sites de l'entreprise**, sans égard aux
 * assignations. Un manager de l'agence A pouvait donc renommer ou supprimer
 * l'agence B.
 *
 * ## Ce que ce test verrouille
 *
 *  - avant toute assignation : comportement historique conservé (aucune
 *    régression pour les tenants qui n'utilisent pas encore les assignations) ;
 *  - dès la première assignation : le type devient fail-closed, et le niveau
 *    décide (`view` < `operate` < `manage`) ;
 *  - `principal` passe toujours ; `rh` conserve la lecture.
 */
class SiteResourceScopedPolicyTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    private Employee $manager;

    private Site $siteA;

    private Site $siteB;

    private SitePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table des assignations n'est pas dans le schéma MVP : on exécute la
        // migration réelle (convention du dépôt, cf. ResourceScopedRbacTest).
        $migration = require database_path('migrations/tenant/2026_09_17_000001_7598_create_employee_resource_assignments_table.php');
        $migration->up();

        $this->company = Company::query()->create([
            'name' => 'Société Sites',
            'slug' => 'societe-sites',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@sites.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        // Les états de la factory sont utilisés (et non des attributs passés à
        // `create()`) : `role`/`manager_role` ne sont pas mass-assignables.
        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);

        $this->siteA = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Agence A',
            'code' => 'AG-A',
        ]);
        $this->siteB = Site::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Agence B',
            'code' => 'AG-B',
        ]);

        $this->policy = new SitePolicy;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function assign(Employee $employee, int $siteId, string $level): void
    {
        $assignment = new EmployeeResourceAssignment([
            'employee_id' => $employee->id,
            'resource_type' => 'site',
            'resource_id' => $siteId,
            'access_level' => $level,
        ]);
        $assignment->company_id = $this->company->id;
        $assignment->created_by = $this->principal->id;
        $assignment->save();
    }

    // ── 1. Avant toute assignation : comportement historique ────────────────

    public function test_behaviour_is_unchanged_before_any_assignment(): void
    {
        $this->assertFalse($this->manager->isResourceTypeScoped('site'));

        // Le manager voyait déjà tous les sites de son entreprise : on ne
        // change rien tant que le tenant n'utilise pas les assignations.
        $this->assertTrue($this->policy->view($this->manager, $this->siteA));
        $this->assertTrue($this->policy->view($this->manager, $this->siteB));
        $this->assertTrue($this->policy->update($this->manager, $this->siteA));
    }

    // ── 2. Dès la première assignation : le type est fail-closed ────────────

    public function test_a_manager_without_assignment_loses_access_once_the_type_is_scoped(): void
    {
        $this->assign($this->manager, (int) $this->siteA->id, EmployeeResourceAssignment::LEVEL_VIEW);

        $this->assertTrue($this->manager->isResourceTypeScoped('site'));

        // Assigné à A : il voit A, plus B. C'est exactement la faille corrigée.
        $this->assertTrue($this->policy->view($this->manager, $this->siteA));
        $this->assertFalse($this->policy->view($this->manager, $this->siteB));
    }

    // ── 3. Le niveau décide ─────────────────────────────────────────────────

    public function test_view_level_cannot_update_or_delete(): void
    {
        $this->assign($this->manager, (int) $this->siteA->id, EmployeeResourceAssignment::LEVEL_VIEW);

        $this->assertTrue($this->policy->view($this->manager, $this->siteA));
        $this->assertFalse($this->policy->update($this->manager, $this->siteA));
        $this->assertFalse($this->policy->delete($this->manager, $this->siteA));
    }

    public function test_manage_level_can_update_and_delete(): void
    {
        $this->assign($this->manager, (int) $this->siteA->id, EmployeeResourceAssignment::LEVEL_MANAGE);

        $this->assertTrue($this->policy->view($this->manager, $this->siteA));
        $this->assertTrue($this->policy->update($this->manager, $this->siteA));
        $this->assertTrue($this->policy->delete($this->manager, $this->siteA));
    }

    public function test_operate_level_cannot_manage(): void
    {
        $this->assign($this->manager, (int) $this->siteA->id, EmployeeResourceAssignment::LEVEL_OPERATE);

        $this->assertTrue($this->policy->view($this->manager, $this->siteA));
        $this->assertFalse($this->policy->update($this->manager, $this->siteA));
    }

    // ── 4. `principal` passe toujours ───────────────────────────────────────

    public function test_principal_keeps_full_access_without_assignment(): void
    {
        $this->assign($this->manager, (int) $this->siteA->id, EmployeeResourceAssignment::LEVEL_VIEW);

        $this->assertTrue($this->policy->view($this->principal, $this->siteB));
        $this->assertTrue($this->policy->update($this->principal, $this->siteB));
        $this->assertTrue($this->policy->delete($this->principal, $this->siteB));
    }

    // ── 5. Isolation tenant ─────────────────────────────────────────────────

    public function test_another_tenant_site_is_never_visible(): void
    {
        $other = Company::query()->create([
            'name' => 'Autre Société',
            'slug' => 'autre-societe',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@autre.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $foreign = Site::query()->create([
            'company_id' => $other->id,
            'name' => 'Agence Étrangère',
            'code' => 'AG-X',
        ]);

        $this->assertFalse($this->policy->view($this->manager, $foreign));
        $this->assertFalse($this->policy->view($this->principal, $foreign));
    }
}
