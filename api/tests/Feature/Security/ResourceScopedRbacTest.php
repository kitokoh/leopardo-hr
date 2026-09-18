<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7598 (R1 de l'épique #7597) — socle « accès aux ressources ».
 *
 * Miroir de `DepartmentScopedRbacTest` : on vérifie que l'autorisation peut
 * porter sur une **ressource nommée** et non sur un rôle global, avec la règle
 * de progressivité voulue par l'issue :
 *
 *  - `principal` : tout, sans assignation ;
 *  - `rh` : lecture seule, sans assignation ;
 *  - assigné : selon le niveau (`view` < `operate` < `manage`) ;
 *  - non assigné : refusé **dès lors que** le type est assigné dans
 *    l'entreprise — et comportement inchangé avant la première assignation.
 */
class ResourceScopedRbacTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    private Employee $rh;

    private Employee $employee;

    private int $cameraA;

    private int $cameraB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table de cette tranche n'est pas dans le schéma MVP : on exécute la
        // migration réelle (convention du dépôt, cf. BfIutsBackfillTest).
        $migration = require database_path('migrations/tenant/2026_09_17_000001_7598_create_employee_resource_assignments_table.php');
        $migration->up();

        $this->company = Company::query()->create([
            'name' => 'Restaurant Exemple',
            'slug' => 'restaurant-exemple',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@exemple.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        // Les états de la factory sont utilisés (et non des attributs passés à
        // `create()`) : `role`/`manager_role` ne sont pas mass-assignables —
        // les passer en attributs les ferait tomber silencieusement, et le
        // « principal » ne serait qu'un employé (constat de ce test).
        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->rh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->cameraA = $this->makeCamera('Almadies');
        $this->cameraB = $this->makeCamera('Plateau');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeEmployee(): Employee
    {
        return Employee::factory()->create(['company_id' => $this->company->id]);
    }

    private function makeCamera(string $name): int
    {
        return (int) DB::table('cameras')->insertGetId([
            'company_id' => $this->company->id,
            'name' => $name,
            'rtsp_url' => 'rtsp://camera.test/'.$name,
            'created_by' => $this->principal->id,
        ]);
    }

    private function assign(Employee $employee, int $resourceId, string $level, string $type = 'camera'): void
    {
        $assignment = new EmployeeResourceAssignment([
            'employee_id' => $employee->id,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'access_level' => $level,
        ]);
        $assignment->company_id = $this->company->id;
        $assignment->created_by = $this->principal->id;
        $assignment->save();
    }

    // ── 1. Avant toute assignation : comportement historique conservé ───────

    public function test_behaviour_is_unchanged_before_any_assignment(): void
    {
        $this->assertFalse($this->rh->isResourceTypeScoped('camera'));
        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA));
        $this->assertNull($this->employee->accessibleResourceIds('camera'));
    }

    // ── 2. `principal` et `rh` n'ont pas besoin d'assignation ───────────────

    public function test_principal_has_everything_without_assignment(): void
    {
        $this->assign($this->employee, $this->cameraA, EmployeeResourceAssignment::LEVEL_MANAGE);

        $this->assertTrue($this->principal->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_MANAGE));
        $this->assertTrue($this->principal->hasResourceAccess('camera', $this->cameraB, EmployeeResourceAssignment::LEVEL_MANAGE));
        $this->assertNull($this->principal->accessibleResourceIds('camera'));
    }

    public function test_rh_reads_everything_but_cannot_operate(): void
    {
        $this->assertTrue($this->rh->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_VIEW));
        $this->assertTrue($this->rh->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_OPERATE));
        $this->assertNull($this->rh->accessibleResourceIds('camera'));
    }

    // ── 3. Assigné : le niveau décide, et rien de plus ──────────────────────

    public function test_assigned_level_is_enforced(): void
    {
        $this->assign($this->employee, $this->cameraA, EmployeeResourceAssignment::LEVEL_OPERATE);

        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_VIEW));
        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_OPERATE));
        $this->assertFalse($this->employee->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_MANAGE));
        $this->assertSame([$this->cameraA], $this->employee->accessibleResourceIds('camera'));
        $this->assertSame([], $this->employee->accessibleResourceIds('camera', EmployeeResourceAssignment::LEVEL_MANAGE));
    }

    public function test_manage_level_satisfies_below(): void
    {
        $this->assign($this->employee, $this->cameraA, EmployeeResourceAssignment::LEVEL_MANAGE);

        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_OPERATE));
        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA, EmployeeResourceAssignment::LEVEL_VIEW));
    }

    // ── 4. Progressivité : la 1re assignation rend le type fail-closed ──────

    public function test_first_assignment_scopes_the_type_fail_closed(): void
    {
        $other = $this->makeEmployee();

        $this->assertTrue($other->hasResourceAccess('camera', $this->cameraA));

        $this->assign($this->employee, $this->cameraA, EmployeeResourceAssignment::LEVEL_VIEW);

        $this->assertTrue($this->employee->isResourceTypeScoped('camera'));
        // Le type est désormais scopé : le non-assigné est refusé, y compris
        // sur une caméra qu'il « voyait » une seconde plus tôt.
        $this->assertFalse($other->hasResourceAccess('camera', $this->cameraA));
        $this->assertFalse($other->hasResourceAccess('camera', $this->cameraB));
        $this->assertSame([], $other->accessibleResourceIds('camera'));

        // Et l'assigné ne voit QUE la sienne.
        $this->assertTrue($this->employee->hasResourceAccess('camera', $this->cameraA));
        $this->assertFalse($this->employee->hasResourceAccess('camera', $this->cameraB));
    }

    public function test_unknown_level_requirement_fails_closed(): void
    {
        $this->assertFalse($this->employee->hasResourceAccess('camera', $this->cameraA, 'admin'));
    }

    // ── 5. API : lecture et remplacement complet du jeu ─────────────────────

    public function test_principal_lists_and_replaces_assignments(): void
    {
        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'camera', 'resource_id' => $this->cameraA, 'access_level' => 'manage'],
                ['resource_type' => 'camera', 'resource_id' => $this->cameraB, 'access_level' => 'view'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'resource_type' => 'camera',
                'resource_id' => $this->cameraA,
                'access_level' => 'manage',
                'resource_label' => 'Almadies',
            ]);

        // Remplacement : ne garder que la caméra B révoque l'accès à A.
        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'camera', 'resource_id' => $this->cameraB, 'access_level' => 'operate'],
            ],
        ])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['resource_id' => $this->cameraB, 'access_level' => 'operate']);

        $this->assertNull($this->employee->refresh()->resourceAssignmentLevel('camera', $this->cameraA));
        $this->assertSame('operate', $this->employee->refresh()->resourceAssignmentLevel('camera', $this->cameraB));

        $this->getJson("/api/v1/employees/{$this->employee->id}/resource-assignments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_empty_payload_revokes_everything(): void
    {
        $this->assign($this->employee, $this->cameraA, EmployeeResourceAssignment::LEVEL_VIEW);

        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", ['assignments' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Plus aucune assignation : le type redevient non scopé (comportement
        // historique), c'est la réciproque assumée de la progressivité.
        $this->assertFalse($this->employee->refresh()->isResourceTypeScoped('camera'));
    }

    // ── 6. Fail-closed : types et ressources inconnus ──────────────────────

    public function test_unknown_resource_type_is_rejected(): void
    {
        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'yacht', 'resource_id' => 1, 'access_level' => 'view'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['assignments.0.resource_type']);

        $this->getJson('/api/v1/resources/yacht')->assertStatus(404);
    }

    public function test_resource_from_another_company_is_rejected(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Autre Restaurant',
            'slug' => 'autre-restaurant',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'autre@exemple.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $foreignCamera = (int) DB::table('cameras')->insertGetId([
            'company_id' => $otherCompany->id,
            'name' => 'Caméra étrangère',
            'rtsp_url' => 'rtsp://camera.test/foreign',
            'created_by' => $this->principal->id,
        ]);

        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'camera', 'resource_id' => $foreignCamera, 'access_level' => 'view'],
            ],
        ])->assertStatus(422)->assertJson(['error' => 'RESOURCE_NOT_FOUND']);
    }

    // ── 7. Réservé au principal, jamais en travers du tenant ───────────────

    public function test_non_principal_cannot_manage_or_read_assignments(): void
    {
        Sanctum::actingAs($this->rh);
        $this->getJson("/api/v1/employees/{$this->employee->id}/resource-assignments")->assertStatus(403);
        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", ['assignments' => []])->assertStatus(403);

        Sanctum::actingAs($this->employee);
        $this->getJson("/api/v1/employees/{$this->employee->id}/resource-assignments")->assertStatus(403);
        $this->getJson('/api/v1/resources/camera')->assertStatus(403);
    }

    public function test_catalog_lists_company_resources_for_principal(): void
    {
        Sanctum::actingAs($this->principal);

        $this->getJson('/api/v1/resources/camera')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['label' => 'Almadies'])
            ->assertJsonFragment(['label' => 'Plateau']);
    }

    // ── 8. Chaque geste est audité ─────────────────────────────────────────

    public function test_assignment_changes_are_audited(): void
    {
        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'camera', 'resource_id' => $this->cameraA, 'access_level' => 'view'],
            ],
        ])->assertOk();

        $created = DB::table('audit_logs')
            ->where('auditable_type', EmployeeResourceAssignment::class)
            ->where('action', 'created')
            ->count();
        $this->assertGreaterThanOrEqual(1, $created, 'la pose d’un accès doit être auditée');

        $this->putJson("/api/v1/employees/{$this->employee->id}/resource-assignments", ['assignments' => []])
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, DB::table('audit_logs')
            ->where('auditable_type', EmployeeResourceAssignment::class)
            ->where('action', 'deleted')
            ->count(), 'la révocation d’un accès doit être auditée');
    }
}
