<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Enums\DelegablePermission;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * IAM-L5 (#9095) — le BACKEND des rôles personnalisés, tel que l'écran #9518
 * le consomme.
 *
 * ## Ce que ce fichier ajoute aux tests de plafond existants
 *
 * `CustomRoleDelegationTest` verrouille le PLAFOND (permissions ⊆ créateur).
 * Ce fichier verrouille le CONTRAT et l'ATTRIBUTION :
 *
 *  - la forme exacte de la réponse (`data.roles`, `data.catalog`,
 *    `holder_count`) — celle que l'écran livré lit réellement ;
 *  - le catalogue BORNÉ par le plafond de l'acteur (un `rh` ne se voit pas
 *    proposer `is_principal`) ;
 *  - l'attribution d'un rôle à un collaborateur, qui rend `holder_count` réel ;
 *  - l'isolation tenant de l'attribution (pas d'attribution croisée) ;
 *  - la révocation en cascade (révoquer un rôle révoque ses porteurs).
 */
class CustomRoleBackendContractTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    private Employee $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = $this->makeCompany('L5 Contrat', 'l5-contrat');
        $this->principal = $this->makeManager($this->company, 'principal@l5c.test', 'principal');
        $this->rh = $this->makeManager($this->company, 'rh@l5c.test', 'rh');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function the_index_exposes_the_shape_the_delivered_ui_reads(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $response = $this->getJson('/api/v1/company/roles')->assertOk();

        // L'écran #9518 lit `data.roles` et `data.catalog` — pas `data` = liste.
        $response->assertJsonStructure([
            'data' => [
                'roles',
                'catalog' => [['key', 'label', 'category']],
            ],
        ]);

        $this->assertIsArray($response->json('data.roles'));
        $this->assertNotEmpty($response->json('data.catalog'));
    }

    /** @test */
    public function the_catalog_is_bounded_by_the_actor_ceiling(): void
    {
        // Un `rh` ne détient pas `is_principal` : le catalogue qu'il reçoit ne
        // doit pas le lui proposer, sinon il composerait un rôle voué au 422.
        Sanctum::actingAs($this->rh, ['*']);

        $keys = array_column($this->getJson('/api/v1/company/roles')->assertOk()->json('data.catalog'), 'key');

        $this->assertNotContains(DelegablePermission::IsPrincipal->value, $keys);
        $this->assertNotContains(DelegablePermission::ManageDelegation->value, $keys);
        $this->assertContains(DelegablePermission::CreateEmployees->value, $keys);
    }

    /** @test */
    public function the_principal_sees_the_whole_catalog(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $keys = array_column($this->getJson('/api/v1/company/roles')->assertOk()->json('data.catalog'), 'key');

        foreach (DelegablePermission::keys() as $key) {
            $this->assertContains($key, $keys);
        }
    }

    /** @test */
    public function assigning_a_role_makes_the_holder_count_real(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $target = $this->makeEmployee($this->company, 'caissier@l5c.test');

        $this->postJson("/api/v1/company/roles/{$roleId}/assign", [
            'employee_id' => $target->id,
        ])->assertOk()
            ->assertJsonPath('data.holder_count', 1);

        // Le compteur est bien celui de l'écran.
        $this->assertSame(1, $this->getJson('/api/v1/company/roles')->json('data.roles.0.holder_count'));
    }

    /** @test */
    public function assigning_twice_is_idempotent(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Chef de rang',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $target = $this->makeEmployee($this->company, 'chef@l5c.test');

        $this->postJson("/api/v1/company/roles/{$roleId}/assign", ['employee_id' => $target->id])->assertOk();
        $this->postJson("/api/v1/company/roles/{$roleId}/assign", ['employee_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.holder_count', 1);

        $this->assertSame(
            1,
            DB::table('shared_tenants.employee_role_assignments')
                ->where('role_assignment_id', $roleId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    /** @test */
    public function a_role_cannot_be_assigned_to_an_employee_of_another_tenant(): void
    {
        // Sans le filtre `company_id`, un principal pourrait attribuer un rôle
        // à l'employé d'une autre entreprise par identifiant deviné.
        $otherCompany = $this->makeCompany('L5 Autre', 'l5-autre');
        $foreigner = $this->makeEmployee($otherCompany, 'etranger@l5a.test');

        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/v1/company/roles/{$roleId}/assign", [
            'employee_id' => $foreigner->id,
        ])->assertStatus(404);

        $this->assertSame(
            0,
            DB::table('shared_tenants.employee_role_assignments')
                ->where('role_assignment_id', $roleId)
                ->count(),
        );
    }

    /** @test */
    public function revoking_a_role_also_revokes_its_holders(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $target = $this->makeEmployee($this->company, 'porteur@l5c.test');
        $this->postJson("/api/v1/company/roles/{$roleId}/assign", ['employee_id' => $target->id])->assertOk();

        $this->deleteJson("/api/v1/company/roles/{$roleId}")->assertOk();

        // Sinon des collaborateurs porteraient un rôle qui n'existe plus.
        $this->assertSame(
            0,
            DB::table('shared_tenants.employee_role_assignments')
                ->where('role_assignment_id', $roleId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    /** @test */
    public function unassigning_removes_the_holder(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $target = $this->makeEmployee($this->company, 'retire@l5c.test');
        $this->postJson("/api/v1/company/roles/{$roleId}/assign", ['employee_id' => $target->id])->assertOk();

        $this->deleteJson("/api/v1/company/roles/{$roleId}/assign/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.holder_count', 0);
    }

    /** @test */
    public function a_non_principal_cannot_assign_roles(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $target = $this->makeEmployee($this->company, 'cible@l5c.test');

        Sanctum::actingAs($this->rh, ['*']);

        $this->postJson("/api/v1/company/roles/{$roleId}/assign", [
            'employee_id' => $target->id,
        ])->assertStatus(403)
            ->assertJsonPath('error', 'DELEGATION_FORBIDDEN');
    }

    /** @test */
    public function a_revoked_role_cannot_be_assigned_again(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $roleId = (int) $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ])->assertStatus(201)->json('data.id');

        $this->deleteJson("/api/v1/company/roles/{$roleId}")->assertOk();

        $target = $this->makeEmployee($this->company, 'tardif@l5c.test');

        // On ne ressuscite pas un rôle par une attribution.
        $this->postJson("/api/v1/company/roles/{$roleId}/assign", [
            'employee_id' => $target->id,
        ])->assertStatus(404);
    }

    private function makeManager(Company $company, string $email, string $managerRole): Employee
    {
        $manager = new Employee(['email' => $email]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => ucfirst($managerRole),
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        return $manager;
    }

    private function makeEmployee(Company $company, string $email): Employee
    {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'first_name' => 'Employe',
            'last_name' => 'Test',
            'role' => 'employee',
            'manager_role' => null,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function makeCompany(string $name, string $slug): Company
    {
        /** @var Company $company */
        $company = Company::query()->create([
            'name' => $name,
            'slug' => $slug.'-'.uniqid(),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slug.'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        return $company;
    }
}
