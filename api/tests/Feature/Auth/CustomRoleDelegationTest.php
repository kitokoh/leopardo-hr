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
 * IAM-L5 (#9095) — rôles personnalisés de tenant.
 *
 * ## Ce que ces tests verrouillent
 *
 * Tant qu'aucune table de rôles n'existait, le scénario « un rôle personnalisé
 * dépasse les droits de son créateur » était **structurellement impossible**.
 * Dès qu'on introduit `role_assignments`, il devient **critique** : ces tests
 * sont donc écrits AVANT la table, et ils échouent si le plafond disparaît.
 *
 * ## Le plafond, en une phrase
 *
 * `permissions ⊆ capabilities(créateur)`, à la création **et** à la
 * modification. Un `rh` ne peut pas fabriquer un rôle portant `is_principal`
 * ni `delegation.manage` — il ne les détient pas.
 */
class CustomRoleDelegationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    private Employee $rh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = $this->company();
        $this->principal = $this->manager($this->company, 'principal@l5.test', 'principal');
        $this->rh = $this->manager($this->company, 'rh@l5.test', 'rh');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Scénario #11 (a) — le plafond du créateur.
     *
     * Un `rh` détient `can_create_employees` mais PAS `is_principal`. Il ne
     * peut donc pas fabriquer un rôle qui l'accorde.
     */
    public function test_a_custom_role_cannot_exceed_its_creator(): void
    {
        Sanctum::actingAs($this->rh, ['*']);

        $response = $this->postJson('/api/v1/company/roles', [
            'name' => 'Caissier',
            'permissions' => [
                DelegablePermission::CreateEmployees->value,
                DelegablePermission::IsPrincipal->value,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DELEGATION_EXCEEDS_ACTOR');
        $response->assertJsonPath('excess', [DelegablePermission::IsPrincipal->value]);

        $this->assertSame(
            0,
            DB::table('shared_tenants.role_assignments')->where('company_id', $this->company->id)->count(),
            'Aucun rôle ne doit être créé quand le plafond est dépassé.',
        );
    }

    /**
     * Scénario #11 (b) — un non-principal ne peut pas accorder
     * `delegation.manage`.
     *
     * C'est la permission qui permet de fabriquer des rôles : si un `rh`
     * pouvait se l'attribuer, il contournerait le plafond en deux temps.
     */
    public function test_a_non_principal_cannot_grant_delegation_manage(): void
    {
        Sanctum::actingAs($this->rh, ['*']);

        $response = $this->postJson('/api/v1/company/roles', [
            'name' => 'Faux Principal',
            'permissions' => [DelegablePermission::ManageDelegation->value],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DELEGATION_EXCEEDS_ACTOR');
    }

    /**
     * Scénario #11 (c) — un rôle est borné à SON tenant.
     *
     * Un principal du tenant A ne voit ni ne modifie les rôles du tenant B.
     */
    public function test_a_custom_role_is_scoped_to_its_tenant(): void
    {
        $otherCompany = $this->company('L5 Autre Tenant', 'l5-autre-tenant');

        $otherRoleId = DB::table('shared_tenants.role_assignments')->insertGetId([
            'company_id' => $otherCompany->id,
            'name' => 'Role Autre Tenant',
            'permissions' => json_encode([DelegablePermission::ViewDashboard->value], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->principal, ['*']);

        // Le rôle de l'autre tenant n'apparaît pas dans la liste.
        $list = $this->getJson('/api/v1/company/roles');
        $list->assertOk();
        $this->assertSame([], $list->json('data'));

        // Et il n'est pas modifiable depuis ce tenant.
        $update = $this->putJson('/api/v1/company/roles/'.$otherRoleId, [
            'permissions' => [DelegablePermission::ViewDashboard->value],
        ]);
        $update->assertStatus(404);
    }

    /**
     * Scénario #11 (d) — un principal ne délègue pas plus qu'il ne détient.
     *
     * Le principal détient tout le registre : il peut donc créer un rôle
     * complet. Mais une permission HORS registre reste refusée (fail-closed).
     */
    public function test_a_principal_cannot_delegate_beyond_the_registry(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $ok = $this->postJson('/api/v1/company/roles', [
            'name' => 'Chef de rang',
            'permissions' => [
                DelegablePermission::ViewDashboard->value,
                DelegablePermission::ViewFleet->value,
            ],
        ]);
        $ok->assertStatus(201);

        $refused = $this->postJson('/api/v1/company/roles', [
            'name' => 'Role Invente',
            'permissions' => ['can_do_anything'],
        ]);
        $refused->assertStatus(422);
    }

    /**
     * Scénario #11 (e) — un rôle de verticale est borné à sa verticale.
     *
     * La verticale est portée par la ligne : un rôle marqué `restaurant` ne
     * peut pas être présenté comme un rôle d'une autre verticale.
     */
    public function test_a_vertical_role_is_bounded_to_its_vertical(): void
    {
        Sanctum::actingAs($this->principal, ['*']);

        $created = $this->postJson('/api/v1/company/roles', [
            'name' => 'Chef de rang',
            'permissions' => [DelegablePermission::ViewDashboard->value],
            'vertical' => 'restaurant',
        ]);
        $created->assertStatus(201);
        $created->assertJsonPath('data.vertical', 'restaurant');

        $list = $this->getJson('/api/v1/company/roles');
        $list->assertOk();
        $this->assertSame('restaurant', $list->json('data.0.vertical'));

        // La révocation est effective à la requête suivante.
        $roleId = (int) $created->json('data.id');
        $this->deleteJson('/api/v1/company/roles/'.$roleId)->assertOk();

        $after = $this->getJson('/api/v1/company/roles');
        $after->assertOk();
        $this->assertSame([], $after->json('data'));
    }

    private function manager(Company $company, string $email, string $managerRole): Employee
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

    private function company(string $name = 'L5 Tenant', string $slug = 'l5-tenant'): Company
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
