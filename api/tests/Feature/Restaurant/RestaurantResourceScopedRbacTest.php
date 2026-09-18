<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * Issue #7599 (R2 de l'épique #7597) — RBAC ressource-scopé du pilote
 * Restaurant, au niveau HTTP.
 *
 * Critères de l'issue :
 *  - le gérant de branche (`manage`) gère SON restaurant et voit refusé (403)
 *    sur l'autre ;
 *  - le serveur (`operate`) ne touche que les commandes/POS de SA branche ;
 *  - l'employé sans assignation ne lit plus les données métier dès que le
 *    scoping est actif (fail-closed) ;
 *  - les listings sont bornés aux branches accessibles
 *    (`accessibleResourceIds`).
 */
class RestaurantResourceScopedRbacTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function makeBranch(Company $company): RestaurantBranch
    {
        /** @var RestaurantBranch $branch */
        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());

        return $branch;
    }

    private function makeOrder(Company $company, RestaurantBranch $branch): RestaurantOrder
    {
        /** @var RestaurantOrder $order */
        $order = app(TenantManager::class)->withinTenant($company, fn (): RestaurantOrder => RestaurantOrder::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'open',
            'currency' => $branch->currency,
        ]));

        return $order;
    }

    public function test_branch_manager_manages_his_branch_and_is_denied_on_the_other(): void
    {
        $company = $this->company();
        $mine = $this->makeBranch($company);
        $other = $this->makeBranch($company);

        $manager = $this->employee($company);
        $this->assignResourceAccess($manager, 'restaurant_branch', $mine->id, 'manage');
        Sanctum::actingAs($manager);

        // Gère SA succursale (zone = plan de salle, niveau manage).
        // `status` explicite : le défaut de colonne manque dans certains
        // environnements de test (même quirk que sur main — cf. rapport PR).
        $this->postJson('/api/v1/restaurant/zones', [
            'name' => 'Terrasse',
            'branch_id' => $mine->id,
            'status' => 'active',
        ])->assertStatus(201);

        // Refusé sur L'AUTRE succursale.
        $this->postJson('/api/v1/restaurant/zones', [
            'name' => 'Terrasse pirate',
            'branch_id' => $other->id,
        ])->assertStatus(403);

        // Et l'autre succursale n'apparaît pas dans SON listing.
        $ids = array_column((array) $this->getJson('/api/v1/restaurant/branches?per_page=100')->assertStatus(200)->json('data'), 'id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_server_operates_only_his_branch_orders(): void
    {
        $company = $this->company();
        $mine = $this->makeBranch($company);
        $other = $this->makeBranch($company);

        $server = $this->employee($company);
        $this->assignResourceAccess($server, 'restaurant_branch', $mine->id, 'operate');
        Sanctum::actingAs($server);

        // Commande sur SA branche : autorisé (operate).
        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $mine->id,
            'order_type' => 'takeaway',
        ])->assertStatus(201);

        // Commande sur L'AUTRE branche : refusé.
        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $other->id,
            'order_type' => 'takeaway',
        ])->assertStatus(403);

        // Une commande existante de l'autre branche est invisible (view).
        $foreignOrder = $this->makeOrder($company, $other);
        $this->getJson("/api/v1/restaurant/orders/{$foreignOrder->id}")->assertStatus(403);

        // Et absente du listing.
        $ids = array_column((array) $this->getJson('/api/v1/restaurant/orders?per_page=100')->assertStatus(200)->json('data'), 'id');
        $this->assertNotContains($foreignOrder->id, $ids);

        // `operate` ne donne pas la gestion : le serveur ne crée pas de zone.
        $this->postJson('/api/v1/restaurant/zones', [
            'name' => 'Zone interdite',
            'branch_id' => $mine->id,
        ])->assertStatus(403);
    }

    public function test_unassigned_employee_is_fail_closed_once_scoping_is_active(): void
    {
        $company = $this->company();
        $branch = $this->makeBranch($company);
        $order = $this->makeOrder($company, $branch);

        // Le scoping devient actif à la première assignation du type.
        $someoneElse = $this->employee($company);
        $this->assignResourceAccess($someoneElse, 'restaurant_branch', $branch->id, 'view');

        $unassigned = $this->employee($company);
        Sanctum::actingAs($unassigned);

        // Ne lit plus les données métier : détail refusé, listing vide.
        $this->getJson("/api/v1/restaurant/orders/{$order->id}")->assertStatus(403);
        $this->assertSame([], $this->getJson('/api/v1/restaurant/orders?per_page=100')->assertStatus(200)->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/restaurant/branches?per_page=100')->assertStatus(200)->json('data'));
    }

    public function test_behaviour_is_unchanged_before_any_assignment(): void
    {
        $company = $this->company();
        $branch = $this->makeBranch($company);
        $order = $this->makeOrder($company, $branch);

        $employee = $this->employee($company);
        Sanctum::actingAs($employee);

        // Avant la première assignation : lecture historique conservée…
        $this->getJson("/api/v1/restaurant/orders/{$order->id}")->assertStatus(200);

        // …et l'écriture reste réservée à principal/rh.
        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'takeaway',
        ])->assertStatus(403);
    }
}
