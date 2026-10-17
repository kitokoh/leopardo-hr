<?php

declare(strict_types=1);

namespace Tests\Unit\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use Tests\TestCase;

/**
 * REST-OS-6 (#8608) — matrice rôles × opérations des permissions
 * `restaurant.*` opérationnelles.
 *
 * Avant ce lot, `restaurant.manage|manager|server|kitchen|rider` étaient des
 * constantes DOCUMENTAIRES : aucun call site ne les consommait, aucune règle
 * effective n'existait. Ce test verrouille la règle EFFECTIVE
 * (`RestaurantPermissions::can*`) et le fail-closed :
 *
 *  - chaque rôle assignable (`manager_role`) est testé sur chaque opération ;
 *  - un rôle non autorisé est refusé (jamais un « oui » implicite) ;
 *  - une permission inconnue est refusée pour tout le monde.
 *
 * Les Gates `restaurant.*` enregistrés dans AuthServiceProvider délèguent à
 * ces mêmes méthodes (source unique) : tester la méthode prouve le Gate.
 */
class RestaurantPermissionMatrixTest extends TestCase
{
    /**
     * Rôles assignables via `manager_role` (contrainte CHECK
     * `employees_manager_role_check`, migration 2026_10_16_140002_6302).
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
            'manager' => 'manager',
            'server' => 'server',
            'kitchen' => 'kitchen',
            'rider' => 'rider',
            'cashier' => 'cashier',
            'stock' => 'stock',
        ];
    }

    private function actor(string $managerRole): Employee
    {
        /** @var Employee $actor */
        $actor = new Employee;
        $actor->role = 'manager';
        $actor->manager_role = $managerRole;

        return $actor;
    }

    /**
     * Matrice attendue : opération => rôles autorisés.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function operationMatrixProvider(): array
    {
        return [
            'manage' => ['canManage', ['principal', 'rh']],
            'manager' => ['canManageFloor', ['principal', 'rh', 'manager']],
            'server' => ['canServe', ['principal', 'rh', 'manager', 'server', 'cashier']],
            'kitchen' => ['canWorkKitchen', ['principal', 'rh', 'manager', 'kitchen']],
            'rider' => ['canDeliver', ['principal', 'rh', 'manager', 'rider']],
        ];
    }

    /**
     * @dataProvider operationMatrixProvider
     *
     * @param  list<string>  $allowed
     */
    public function test_matrix_allows_only_expected_roles(string $method, array $allowed): void
    {
        $permissions = new RestaurantPermissions;

        foreach ($this->assignableRoles() as $role) {
            $expected = in_array($role, $allowed, true);
            $actual = $permissions->{$method}($this->actor($role));

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    '%s::%s() doit %s le rôle « %s » (fail-closed).',
                    RestaurantPermissions::class,
                    $method,
                    $expected ? 'autoriser' : 'REFUSER',
                    $role,
                ),
            );
        }
    }

    public function test_employee_role_is_never_authorized(): void
    {
        $permissions = new RestaurantPermissions;

        /** @var Employee $actor */
        $actor = new Employee;
        $actor->role = 'employee';
        $actor->manager_role = null;

        foreach (['canManage', 'canManageFloor', 'canServe', 'canWorkKitchen', 'canDeliver'] as $method) {
            $this->assertFalse(
                $permissions->{$method}($actor),
                "Un employé simple ne doit jamais passer {$method}().",
            );
        }
    }

    public function test_can_dispatches_each_permission_to_its_rule(): void
    {
        $permissions = new RestaurantPermissions;
        $principal = $this->actor('principal');
        $server = $this->actor('server');

        $this->assertTrue($permissions->can($principal, RestaurantPermissions::MANAGE));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::MANAGER));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::SERVER));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::KITCHEN));
        $this->assertTrue($permissions->can($principal, RestaurantPermissions::RIDER));

        // Un serveur ne gère pas la configuration ni la salle, mais sert.
        $this->assertFalse($permissions->can($server, RestaurantPermissions::MANAGE));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::MANAGER));
        $this->assertTrue($permissions->can($server, RestaurantPermissions::SERVER));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::KITCHEN));
        $this->assertFalse($permissions->can($server, RestaurantPermissions::RIDER));
    }

    public function test_unknown_permission_is_fail_closed_for_every_role(): void
    {
        $permissions = new RestaurantPermissions;

        foreach ($this->assignableRoles() as $role) {
            $this->assertFalse(
                $permissions->can($this->actor($role), 'restaurant.does_not_exist'),
                "La permission inconnue doit être refusée pour « {$role} ».",
            );
        }
    }
}
