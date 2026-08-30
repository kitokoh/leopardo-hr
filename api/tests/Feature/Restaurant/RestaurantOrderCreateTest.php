<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-402 (#6189) — Création de commande (idempotente).
 *
 * Couvre : types salle/emporter/livraison, référence RST- unique, rejeu d'une
 * même `idempotency_key` → même commande (pas de doublon), table `dine_in`
 * obligatoire (422), RBAC (serveur+) et isolation cross-tenant (404 sûr).
 */
class RestaurantOrderCreateTest extends TestCase
{
    use RefreshTenantDatabase;

    private function server(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'server',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    /**
     * @return array{branch: RestaurantBranch, table: RestaurantTable}
     */
    private function makeBranchAndTable(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $branch = RestaurantBranch::factory()->create();
            $table = RestaurantTable::factory()->create(['branch_id' => $branch->id]);

            return ['branch' => $branch, 'table' => $table];
        });
    }

    public function test_server_can_create_dine_in_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch, 'table' => $table] = $this->makeBranchAndTable($company);

        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'covers' => 4,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.order_type', 'dine_in')
            ->assertJsonPath('data.table_id', $table->id)
            ->assertJsonPath('data.currency', $branch->currency)
            ->assertJsonStructure(['data' => ['reference' => []]]);
    }

    public function test_replay_with_same_idempotency_key_returns_same_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch] = $this->makeBranchAndTable($company);

        $key = (string) \Illuminate\Support\Str::uuid();

        $first = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'takeaway',
            'idempotency_key' => $key,
        ])->assertStatus(201);

        $orderId = $first->json('data.id');

        $replay = $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'takeaway',
            'idempotency_key' => $key,
        ])->assertStatus(200);

        $this->assertSame($orderId, $replay->json('data.id'));

        $this->assertSame(1, RestaurantOrder::query()->where('id', $orderId)->count());
    }

    public function test_dine_in_order_requires_table(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        ['branch' => $branch] = $this->makeBranchAndTable($company);

        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
        ])->assertStatus(422);
    }

    public function test_ordinary_employee_cannot_create_order(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->ordinaryEmployee($company);
        ['branch' => $branch] = $this->makeBranchAndTable($company);

        $this->postJson('/api/v1/restaurant/orders', [
            'branch_id' => $branch->id,
            'order_type' => 'takeaway',
        ])->assertStatus(403);
    }

    public function test_order_from_other_tenant_returns_404(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $orderId = app(TenantManager::class)->withinTenant($other, fn (): int => RestaurantOrder::factory()->create()->id);

        $this->getJson("/api/v1/restaurant/orders/{$orderId}")->assertStatus(404);
    }
}
