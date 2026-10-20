<?php

declare(strict_types=1);

namespace Tests\Unit\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\Support\AssignsResourceAccess;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * REST-OS-6 (#8608) — matrice roles x operations des permissions
 * `restaurant.*` operationnelles.
 *
 * Avant ce lot, `restaurant.manage|manager|server|kitchen|rider` etaient des
 * constantes DOCUMENTAIRES : aucun call site ne les consommait, aucune regle
 * effective n'existait. Ce test verrouille la regle EFFECTIVE
 * (`RestaurantPermissions::can*`) sur ses DEUX plans :
 *
 *  - plan DIRECTION : `manager_role` assignable (`principal`, `rh`) ;
 *  - plan OPERATIONNEL : assignation `restaurant_branch`
 *    (`view` < `operate` < `manage`) pour les roles metier qui ne sont pas
 *    des valeurs de `manager_role` (epique #7597).
 *
 * Fail-closed verifie a chaque etape : un role non autorise est refuse
 * (jamais un « oui » implicite) et une permission inconnue est refusee pour
 * tout le monde.
 *
 * Les Gates `restaurant.*` enregistres dans AuthServiceProvider delegent a ces
 * memes methodes (source unique) : tester la methode prouve le Gate.
 */
class RestaurantPermissionMatrixTest extends TestCase
{
    use AssignsResourceAccess;
    use CreatesMvpSchema;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $migration = require database_path('migrations/tenant/2026_09_17_000001_7598_create_employee_resource_assignments_table.php');
        $migration->up();

        $this->company = Company::query()->create([
            'name' => 'Restaurant Matrice',
            'slug' => 'restaurant-matrice',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'matrice@exemple.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Valeurs REELLEMENT assignables de `manager_role` (contrainte CHECK
     * `employees_manager_role_check`). Les valeurs metier `manager`, `server`,
     * `kitchen`, `rider`, `cashier`, `stock` en sont ABSENTES : c'est
     * exactement le motif mort que `check-manager-role-enum.sh` interdit.
     *
     * @return array<string, string>
     */
    private function assignableRoles(): array
    {
        return [
            'principal' => 'principal',
            'rh' => 'rh',
            'dept' => 'dept',
            'comptable' => 'comptable',
            'superviseur' => 'superviseur',
            'marketing' => 'marketing',
        ];
    }

    /**
     * Roles metier de la salle, qui ne s'expriment QUE par une assignation de
     * ressource.
     *
     * @return array<string, string>
     */
    private function operationalRoles(): array
    {
        return [
            'manager' => 'manager',
            'server' => 'server',
            'kitchen' => 'kitchen',
            'rider' => 'rider',
            'cashier' => 'cashier',
        ];
    }

    private function director(string $managerRole): Employee
    {
        /** @var Employee $actor */
        $actor = new Employee;
        $actor->role = 'manager';
        $actor->manager_role = $managerRole;
        $actor->company_id = $this->company->id;

        return $actor;
    }

    private function employee(): Employee
    {
        /** @var Employee $actor */
        $actor = Employee::factory()->create(['company_id' => $this->company->id]);

        return $actor;
    }

    /**
     * Matrice attendue du plan DIRECTION (scoping NON actif : aucune
     * assignation `restaurant_branch` dans l'entreprise).
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function directorMatrixProvider(): array
    {
        return [
            'manage' => ['canManage', ['principal', 'rh']],
            'manager' => ['canManageFloor', ['principal', 'rh']],
            'server' => ['canServe', ['principal', 'rh']],
            'kitchen' => ['canWorkKitchen', ['principal', 'rh']],
            'rider' => ['canDeliver', ['principal', 'rh']],
        ];
    }

    /**
     * @dataProvider directorMatrixProvider
     *
     * @param  list<string>  $allowed
     */
    public function test_direction_plan_allows_only_expected_roles(string $method, array $allowed): void
    {
        $permissions = new RestaurantPermissions;

        foreach ($this->assignableRoles() as $role) {
            $expected = in_array($role, $allowed, true);
            $actual = $permissions->{$method}($this->director($role));

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    '%s::%s() doit %s le role « %s » (fail-closed).',
                    RestaurantPermissions::class,
                    $method,
                    $expected ? 'autoriser' : 'REFUSER',
                    $role,
                ),
            );
        }
    }

    /**
     * Un role metier sans assignation de ressource n'obtient RIEN : les
     * valeurs `manager`/`server`/... ne sont pas assignables en
     * `manager_role`, donc le plan direction ne peut pas les couvrir.
     */
    public function test_operational_manager_role_without_assignment_is_refused(): void
    {
        $permissions = new RestaurantPermissions;

        foreach ($this->operationalRoles() as $role) {
            $actor = $this->director($role);

            foreach (['canManage', 'canManageFloor', 'canServe', 'canWorkKitchen', 'canDeliver'] as $method) {
                $this->assertFalse(
                    $permissions->{$method}($actor),
                    "Un role « {$role} » sans assignation de ressource ne doit pas passer {$method}().",
                );
            }
        }
    }

    public function test_employee_role_is_never_authorized(): void
    {
        $permissions = new RestaurantPermissions;
        $actor = $this->employee();

        foreach (['canManage', 'canManageFloor', 'canServe', 'canWorkKitchen', 'canDeliver'] as $method) {
            $this->assertFalse(
                $permissions->{$method}($actor),
                "Un employe simple ne doit jamais passer {$method}().",
            );
        }
    }

    /**
     * Plan OPERATIONNEL : des qu'une assignation `restaurant_branch` existe
     * dans l'entreprise, le scoping devient actif et fail-closed.
     */
    public function test_resource_assignment_opens_operational_permissions(): void
    {
        $permissions = new RestaurantPermissions;

        $kitchen = $this->employee();
        $this->assignResourceAccess($kitchen, RestaurantPermissions::RESOURCE_TYPE, 101, EmployeeResourceAssignment::LEVEL_OPERATE);

        $this->assertTrue($permissions->canWorkKitchen($kitchen), 'Le niveau operate ouvre la cuisine.');
        $this->assertTrue($permissions->canServe($kitchen), 'Le niveau operate ouvre le service.');
        $this->assertTrue($permissions->canDeliver($kitchen), 'Le niveau operate ouvre la livraison.');
        $this->assertFalse($permissions->canManageFloor($kitchen), 'Le niveau operate n\'ouvre pas le pilotage de salle.');
        $this->assertFalse($permissions->canManage($kitchen), 'Le niveau operate n\'ouvre pas la configuration.');

        $floorManager = $this->employee();
        $this->assignResourceAccess($floorManager, RestaurantPermissions::RESOURCE_TYPE, 101, EmployeeResourceAssignment::LEVEL_MANAGE);

        $this->assertTrue($permissions->canManageFloor($floorManager), 'Le niveau manage (>= operate) ouvre le pilotage de salle.');
        $this->assertTrue($permissions->canServe($floorManager), 'manage satisfait operate (niveaux ordonnes).');
        $this->assertFalse($permissions->canManage($floorManager), 'manage sur une succursale n\'ouvre pas la configuration du tenant.');

        $viewer = $this->employee();
        $this->assignResourceAccess($viewer, RestaurantPermissions::RESOURCE_TYPE, 101, EmployeeResourceAssignment::LEVEL_VIEW);

        $this->assertFalse($permissions->canServe($viewer), 'Le niveau view n\'ouvre aucune operation de salle.');
        $this->assertFalse($permissions->canWorkKitchen($viewer), 'Le niveau view n\'ouvre pas la cuisine.');
    }

    /**
     * Scoping actif : un employe NON assigne est refuse (fail-closed), alors
     * qu'il passait par le comportement historique avant la premiere
     * assignation (voir test_direction_plan_allows_only_expected_roles).
     */
    public function test_scoping_is_fail_closed_for_unassigned_actors(): void
    {
        $permissions = new RestaurantPermissions;

        $assigned = $this->employee();
        $this->assignResourceAccess($assigned, RestaurantPermissions::RESOURCE_TYPE, 101, EmployeeResourceAssignment::LEVEL_OPERATE);

        $unassigned = $this->employee();

        $this->assertFalse($permissions->canServe($unassigned));
        $this->assertFalse($permissions->canWorkKitchen($unassigned));
        $this->assertFalse($permissions->canDeliver($unassigned));
        $this->assertFalse($permissions->canManageFloor($unassigned));
    }

    public function test_can_dispatches_each_permission_to_its_rule(): void
    {
        $permissions = new RestaurantPermissions;
        $principal = $this->director('principal');

        $this->assertTrue($permissions->can($principal, RestaurantPermissions::MANAGE));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::MANAGER));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::SERVER));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::KITCHEN));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::RIDER));

        // Un role metier sans assignation ne gere ni ne sert.
        $server = $this->director('server');

        $this->assertFalse($permissions->can($server, RestaurantPermissions::MANAGE));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::MANAGER));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::SERVER));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::KITCHEN));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::RIDER));
    }

    public function test_unknown_permission_is_fail_closed_for_every_role(): void
    {
        $permissions = new RestaurantPermissions;

        foreach ([...$this->assignableRoles(), ...$this->operationalRoles()] as $role) {
            $this->assertFalse(
                $permissions->can($this->director($role), 'restaurant.does_not_exist'),
                "La permission inconnue doit etre refusee pour « {$role} ».",
            );
        }

        $this->assertSame([], $permissions->requiresManagerRoles('restaurant.does_not_exist'));
    }

    /**
     * Le mapping documentaire ne doit JAMAIS reintroduire un role non
     * assignable de `manager_role` : c'est la garde #7600 (R3 de #7597)
     * transposee au niveau du test.
     */
    public function test_requires_manager_roles_only_lists_assignable_values(): void
    {
        $permissions = new RestaurantPermissions;
        $assignable = array_values($this->assignableRoles());

        foreach ([
            RestaurantPermissions::MANAGE,
            RestaurantPermissions::MANAGER,
            RestaurantPermissions::SERVER,
            RestaurantPermissions::KITCHEN,
            RestaurantPermissions::RIDER,
            RestaurantPermissions::REPORTS,
            RestaurantPermissions::PRINT_JOBS_MANAGE,
            RestaurantPermissions::PRINT_JOBS_OPERATE,
        ] as $permission) {
            foreach ($permissions->requiresManagerRoles($permission) as $role) {
                $this->assertContains(
                    $role,
                    $assignable,
                    "requiresManagerRoles({$permission}) expose « {$role} », qui n'est pas une valeur assignable de manager_role.",
                );
            }
        }
    }
}
