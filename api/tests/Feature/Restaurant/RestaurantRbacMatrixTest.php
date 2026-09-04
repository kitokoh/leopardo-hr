<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use App\Modules\RestaurantManager\Policies\RestaurantBranchPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantCategoryPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantHourPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantMenuPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantProductPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantSupplierPolicy;
use App\Modules\RestaurantManager\Policies\RestaurantTablePolicy;
use App\Modules\RestaurantManager\Policies\RestaurantZonePolicy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-306 (#6187) — Matrice RBAC `restaurant.*` de la verticale
 * RestaurantManager (BC-25), testée au niveau POLICY.
 *
 * Le RBAC de la plateforme est porté par les rôles de l'employé
 * (role + manager_role, hasManagerRole) : il n'y a pas de table de
 * permissions. Les permissions `restaurant.*` sont des constantes
 * documentaires (RestaurantPermissions, cf. manifest RESTO-106) mappées sur
 * les rôles et consommées par les Policies du module — cf.
 * docs/architecture/RBAC_RESTAURANT_MATRIX.md.
 *
 * Les policies sont instanciées DIRECTEMENT (pas de routes, pas de Gate) :
 * chaque méthode reçoit l'employé acteur et, le cas échéant, la ressource du
 * référentiel (spec §1.3 règle 2 : fail-closed cross-tenant).
 */
class RestaurantRbacMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Une policy par ressource du référentiel BC-25 + une fabrique de
     * ressource du tenant donné (utilisée après le setUp : jamais dans le
     * provider lui-même, la base n'est pas encore prête).
     *
     * @return array<string, array{0: RestaurantBranchPolicy|RestaurantZonePolicy|RestaurantCategoryPolicy|RestaurantProductPolicy|RestaurantMenuPolicy|RestaurantSupplierPolicy, 1: Closure(string): Model}>
     */
    public static function referentialPoliciesProvider(): array
    {
        return [
            'branch' => [
                new RestaurantBranchPolicy,
                static fn (string $companyId): RestaurantBranch => RestaurantBranch::factory()->create(['company_id' => $companyId]),
            ],
            'zone' => [
                new RestaurantZonePolicy,
                static function (string $companyId): RestaurantZone {
                    $branch = RestaurantBranch::factory()->create(['company_id' => $companyId]);

                    return RestaurantZone::factory()->create([
                        'company_id' => $companyId,
                        'branch_id' => $branch->id,
                    ]);
                },
            ],
            'category' => [
                new RestaurantCategoryPolicy,
                static fn (string $companyId): RestaurantCategory => RestaurantCategory::factory()->create(['company_id' => $companyId]),
            ],
            'product' => [
                new RestaurantProductPolicy,
                static function (string $companyId): RestaurantProduct {
                    $category = RestaurantCategory::factory()->create(['company_id' => $companyId]);
                    $taxRate = RestaurantTaxRate::factory()->create(['company_id' => $companyId]);

                    return RestaurantProduct::factory()->create([
                        'company_id' => $companyId,
                        'category_id' => $category->id,
                        'tax_rate_id' => $taxRate->id,
                    ]);
                },
            ],
            'menu' => [
                new RestaurantMenuPolicy,
                static fn (string $companyId): RestaurantMenu => RestaurantMenu::factory()->create(['company_id' => $companyId]),
            ],
            'supplier' => [
                new RestaurantSupplierPolicy,
                static fn (string $companyId): RestaurantSupplier => RestaurantSupplier::factory()->create(['company_id' => $companyId]),
            ],
        ];
    }

    /**
     * Un principal (manager_role 'principal') et un RH (manager_role 'rh')
     * disposent du droit d'écriture complet sur le référentiel
     * (create/update/delete) ainsi que de la lecture — matrice
     * restaurant.manage / restaurant.manager.
     *
     * @dataProvider referentialPoliciesProvider
     *
     * @param  Closure(string): Model  $resourceFactory
     */
    public function test_principal_and_rh_can_create_update_delete(
        RestaurantBranchPolicy|RestaurantZonePolicy|RestaurantCategoryPolicy|RestaurantProductPolicy|RestaurantMenuPolicy|RestaurantSupplierPolicy $policy,
        Closure $resourceFactory,
    ): void {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        foreach ([$principal, $rh] as $actor) {
            $resource = $resourceFactory($company->id);

            $this->assertTrue($policy->create($actor));
            // @phpstan-ignore-next-line — la ressource est typée Model (union des 6 modèles du référentiel) ; chaque policy attend son modèle concret.
            $this->assertTrue($policy->view($actor, $resource));
            // @phpstan-ignore-next-line — idem pour update/delete.
            $this->assertTrue($policy->update($actor, $resource));
            // @phpstan-ignore-next-line — idem pour update/delete.
            $this->assertTrue($policy->delete($actor, $resource));
        }
    }

    /**
     * Un employé ordinaire (role 'employee', sans manager_role) ne peut pas
     * écrire sur le référentiel (create → false) mais conserve la lecture
     * (viewAny → true) : le référentiel reste consultable par toute l'équipe.
     *
     * @dataProvider referentialPoliciesProvider
     */
    public function test_plain_employee_cannot_create_but_can_view_any(
        RestaurantBranchPolicy|RestaurantZonePolicy|RestaurantCategoryPolicy|RestaurantProductPolicy|RestaurantMenuPolicy|RestaurantSupplierPolicy $policy,
    ): void {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->assertFalse($policy->create($employee));
        $this->assertTrue($policy->viewAny($employee));
    }

    /**
     * Une ressource d'un autre tenant est invisible : view → false, même
     * pour un principal (fail-closed, spec §1.3 règle 2 — 404 sûr, jamais
     * de fuite cross-tenant).
     *
     * @dataProvider referentialPoliciesProvider
     *
     * @param  Closure(string): Model  $resourceFactory
     */
    public function test_view_of_resource_from_another_tenant_is_denied(
        RestaurantBranchPolicy|RestaurantZonePolicy|RestaurantCategoryPolicy|RestaurantProductPolicy|RestaurantMenuPolicy|RestaurantSupplierPolicy $policy,
        Closure $resourceFactory,
    ): void {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        /** @var Employee $principalOfA */
        $principalOfA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);

        $foreignResource = $resourceFactory($companyB->id);

        // @phpstan-ignore-next-line — la ressource est typée Model (union des 6 modèles du référentiel) ; chaque policy attend son modèle concret.
        $this->assertFalse($policy->view($principalOfA, $foreignResource));
    }

    /**
     * Le manager de salle (role 'manager', manager_role 'manager') pilote la
     * salle : create autorisé sur les zones, menus, tables et horaires
     * (restaurant.manager), refusé sur la configuration (restaurant.manage :
     * branches, catégories, produits, fournisseurs).
     *
     * Note : `manager_role = 'manager'` n'est pas encore stockable en base
     * (CHECK `employees_manager_role_check` limité à principal/rh/dept/
     * comptable/superviseur/marketing) — l'acteur est donc construit en
     * mémoire (forceFill, aucune requête), ce qui teste la décision de
     * policy sans dépendre du schéma. À réconcilier avec le schéma RBAC
     * (migration d'extension du CHECK) lors du raccord BC-25.
     */
    public function test_floor_manager_can_create_salle_resources_only(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $floorManager */
        $floorManager = new Employee;
        $floorManager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);

        // Pilotage de la salle : zones, menus, tables et horaires.
        $this->assertTrue((new RestaurantZonePolicy)->create($floorManager));
        $this->assertTrue((new RestaurantMenuPolicy)->create($floorManager));
        $this->assertTrue((new RestaurantTablePolicy)->create($floorManager));
        $this->assertTrue((new RestaurantHourPolicy)->create($floorManager));

        // Configuration du référentiel : réservée au gérant (principal/rh).
        $this->assertFalse((new RestaurantBranchPolicy)->create($floorManager));
        $this->assertFalse((new RestaurantCategoryPolicy)->create($floorManager));
        $this->assertFalse((new RestaurantProductPolicy)->create($floorManager));
        $this->assertFalse((new RestaurantSupplierPolicy)->create($floorManager));
    }

    /**
     * Les constantes documentaires reflètent exactement les permissions
     * déclarées par le manifest de la solution (RESTO-106).
     */
    public function test_permission_constants_match_manifest_declarations(): void
    {
        $manifest = app(SolutionManifest::class);

        $constants = [
            RestaurantPermissions::MANAGE,
            RestaurantPermissions::MANAGER,
            RestaurantPermissions::SERVER,
            RestaurantPermissions::KITCHEN,
            RestaurantPermissions::RIDER,
            RestaurantPermissions::REPORTS,
        ];
        sort($constants);

        $declared = $manifest->permissions();
        sort($declared);

        $this->assertSame($constants, $declared);
    }

    /**
     * Le mapping permission → rôles requis (requiresManagerRoles) suit
     * exactement la matrice docs/architecture/RBAC_RESTAURANT_MATRIX.md.
     */
    public function test_requires_manager_roles_matches_matrix(): void
    {
        $permissions = new RestaurantPermissions;

        $this->assertSame(['principal', 'rh'], $permissions->requiresManagerRoles(RestaurantPermissions::MANAGE));
        $this->assertSame(['principal', 'rh', 'manager'], $permissions->requiresManagerRoles(RestaurantPermissions::MANAGER));

        foreach ([
            RestaurantPermissions::SERVER,
            RestaurantPermissions::KITCHEN,
            RestaurantPermissions::RIDER,
            RestaurantPermissions::REPORTS,
        ] as $permission) {
            $this->assertSame(
                ['principal', 'rh', 'manager', 'server', 'kitchen', 'rider'],
                $permissions->requiresManagerRoles($permission),
            );
        }

        // Fail-closed : permission inconnue → aucun rôle requis.
        $this->assertSame([], $permissions->requiresManagerRoles('restaurant.unknown'));
    }
}
