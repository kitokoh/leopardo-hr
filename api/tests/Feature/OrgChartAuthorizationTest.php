<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4497 — OrgChartController : les routes subordinates/manager-chain étaient
 * accessibles à tout employé authentifié. Garde d'autorisation ajoutée :
 * un employé non-manager ne peut interroger que lui-même (sinon 403), et
 * les managers à périmètre réduit (dept/superviseur) sont bornés par le
 * scope visibleToManager().
 */
class OrgChartAuthorizationTest extends TestCase
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

    public function test_non_manager_gets_403_on_others_subordinates(): void
    {
        $company = Company::factory()->create();

        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $colleague = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/org-chart/{$colleague->id}/subordinates")
            ->assertForbidden();
    }

    public function test_non_manager_gets_403_on_others_manager_chain(): void
    {
        $company = Company::factory()->create();

        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $colleague = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/org-chart/{$colleague->id}/manager-chain")
            ->assertForbidden();
    }

    public function test_non_manager_gets_403_on_unknown_employee_id(): void
    {
        $company = Company::factory()->create();

        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        // #4497 : 403 (pas 404) pour un non-manager — aucun oracle
        // d'existence exploitable par le statut de la réponse.
        $this->getJson('/api/v1/org-chart/999999/subordinates')
            ->assertForbidden();

        $this->getJson('/api/v1/org-chart/999999/manager-chain')
            ->assertForbidden();
    }

    public function test_non_manager_can_query_own_subordinates_and_chain(): void
    {
        $company = Company::factory()->create();

        $actor = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson("/api/v1/org-chart/{$actor->id}/subordinates")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/org-chart/{$actor->id}/manager-chain")
            ->assertOk();
    }

    public function test_manager_still_sees_subordinates_and_emails(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'manager_id' => null,
        ]);

        $subordinate = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'manager_id' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/org-chart/{$manager->id}/subordinates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $subordinate->id, 'email' => $subordinate->email]);
    }

    public function test_manager_gets_404_for_unknown_employee(): void
    {
        $company = Company::factory()->create();

        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        // subordinates : aucun employé ne dépend d'un id inconnu → liste vide (200).
        $this->getJson('/api/v1/org-chart/999999/subordinates')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // manager-chain : l'employé cible n'existe pas → 404.
        $this->getJson('/api/v1/org-chart/999999/manager-chain')->assertNotFound();
    }

    public function test_dept_manager_scope_is_bounded_to_own_department(): void
    {
        $company = Company::factory()->create();

        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => 11,
            'status' => 'active',
            'manager_id' => null,
        ]);

        // Subordonné direct du manager, mais dans un AUTRE département.
        $outside = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'department_id' => 22,
            'status' => 'active',
            'manager_id' => $deptManager->id,
        ]);

        Sanctum::actingAs($deptManager);

        // Le subordonné hors département n'apparaît pas (visibleToManager).
        $this->getJson("/api/v1/org-chart/{$deptManager->id}/subordinates")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // La chaîne d'un employé hors département est hors périmètre → 404.
        $this->getJson("/api/v1/org-chart/{$outside->id}/manager-chain")
            ->assertNotFound();
    }
}
