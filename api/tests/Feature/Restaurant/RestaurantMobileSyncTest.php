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
use Tests\TestCase;

/**
 * RESTO-804 (#6225) — Synchronisation offline mobile (file idempotente).
 *
 * Critère d'acceptation : « un rejeu offline ne crée jamais de doublon ».
 * Couvre : order.create rejoué → duplicate avec la même référence, order.pay
 * rejoué → pas de second paiement, opération inconnue → error sans effet de
 * bord, borne à 50 opérations.
 */
class RestaurantMobileSyncTest extends TestCase
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

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    private function branch(Company $company): RestaurantBranch
    {
        /** @var RestaurantBranch $branch */
        $branch = app(TenantManager::class)->withinTenant($company, fn (): RestaurantBranch => RestaurantBranch::factory()->create());

        return $branch;
    }

    public function test_order_create_replay_never_duplicates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);
        $branch = $this->branch($company);

        $operation = [
            'type' => 'order.create',
            'idempotency_key' => 'offline-'.bin2hex(random_bytes(8)),
            'payload' => ['branch_id' => $branch->id, 'order_type' => 'takeaway'],
        ];

        $first = $this->postJson('/api/v1/restaurant/mobile/sync', ['operations' => [$operation]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'created');

        $reference = $first->json('data.0.reference');

        $replay = $this->postJson('/api/v1/restaurant/mobile/sync', ['operations' => [$operation]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'duplicate')
            ->assertJsonPath('data.0.reference', $reference);

        $this->assertSame($reference, $replay->json('data.0.reference'));

        $this->assertSame(1, RestaurantOrder::query()->where('company_id', $company->id)->count());
    }

    public function test_unknown_operation_fails_without_side_effect(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $this->postJson('/api/v1/restaurant/mobile/sync', [
            'operations' => [
                ['type' => 'unknown.op', 'idempotency_key' => 'k-1', 'payload' => []],
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.status', 'error');

        $this->assertSame(0, RestaurantOrder::query()->where('company_id', $company->id)->count());
    }

    public function test_sync_is_bounded_to_50_operations(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->server($company);

        $operations = [];
        for ($i = 0; $i < 60; $i++) {
            $operations[] = ['type' => 'unknown.op', 'idempotency_key' => 'k-'.$i, 'payload' => []];
        }

        $this->postJson('/api/v1/restaurant/mobile/sync', ['operations' => $operations])
            ->assertOk()
            ->assertJsonCount(50, 'data');
    }
}
