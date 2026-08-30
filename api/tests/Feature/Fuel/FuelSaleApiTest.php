<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Ventes FuelStation — FUEL-008 (issue #5802).
 *
 * Couvre : enregistrement avec montant calculé serveur, idempotence
 * external_id, rejet quantité/prix invalides, session de caisse hors
 * tenant refusée, isolation tenant 404, API paginée manager, self-service
 * pompiste (ses ventes uniquement).
 */
class FuelSaleApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
        ])->assertStatus(401);
    }

    public function test_operator_records_sale_with_server_computed_amount(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence sans plomb',
            'quantity' => 12.5,
            'unit_price' => 150.25,
            'source' => 'manual',
            'notes' => 'Vente au comptoir',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.product', 'Essence sans plomb')
            ->assertJsonPath('data.quantity', 12.5)
            ->assertJsonPath('data.unit_price', 150.25)
            // 12.5 × 150.25 = 1878.125 → arrondi 2 décimales = 1878.13
            ->assertJsonPath('data.amount', 1878.13)
            ->assertJsonPath('data.employee_id', $operator->id)
            ->assertJsonPath('data.source', 'manual');
    }

    public function test_external_id_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $payload = [
            'product' => 'Gazole',
            'quantity' => 30,
            'unit_price' => 140,
            'external_id' => 'POS-2026-0001',
        ];

        /** @var array<string, mixed> $first */
        $first = $this->postJson('/api/v1/fuel-station/sales', $payload)
            ->assertStatus(200)
            ->json('data');

        // Rejeu : même vente renvoyée, aucun doublon.
        /** @var array<string, mixed> $second */
        $second = $this->postJson('/api/v1/fuel-station/sales', $payload)
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, FuelSale::query()->where('company_id', $company->id)->count());

        // Le même external_id dans un AUTRE tenant crée une vente distincte.
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($operatorB);
        /** @var array<string, mixed> $other */
        $other = $this->postJson('/api/v1/fuel-station/sales', $payload)->assertStatus(200)->json('data');
        $this->assertNotSame($first['id'], $other['id']);
    }

    public function test_invalid_quantity_or_price_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 0,
            'unit_price' => 150,
        ])->assertStatus(422);
        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => -3,
            'unit_price' => 150,
        ])->assertStatus(422);
        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => '',
            'quantity' => 10,
            'unit_price' => 150,
        ])->assertStatus(422);
        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => -1,
        ])->assertStatus(422);
        // Le montant fourni par le client doit être ignoré (calcul serveur).
        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 100,
            'amount' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.amount', 1000);
    }

    public function test_cash_session_from_another_tenant_rejected(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $companyA->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($operatorA);
        $foreignSession = FuelCashSession::query()->create([
            'company_id' => $companyB->id,
            'opened_by' => $operatorB->id,
            'status' => 'open',
        ]);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
            'cash_session_id' => $foreignSession->id,
        ])->assertStatus(422);
    }

    public function test_cross_tenant_sale_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($operatorA);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
        ])->json('data.id');

        Sanctum::actingAs($managerB);
        $this->getJson("/api/v1/fuel-station/sales/{$saleId}")->assertStatus(404);
    }

    public function test_manager_gets_paginated_list_and_operator_self_service(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($operatorA);
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'Essence', 'quantity' => 10, 'unit_price' => 150])
            ->assertStatus(200);
        Sanctum::actingAs($operatorB);
        $this->postJson('/api/v1/fuel-station/sales', ['product' => 'Gazole', 'quantity' => 20, 'unit_price' => 140])
            ->assertStatus(200);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/fuel-station/sales?per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/fuel-station/me/sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product', 'Essence');
    }

    public function test_operator_cannot_view_another_operators_sale(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operatorA */
        $operatorA = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $operatorB */
        $operatorB = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($operatorA);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
        ])->json('data.id');

        Sanctum::actingAs($operatorB);
        $this->getJson("/api/v1/fuel-station/sales/{$saleId}")->assertStatus(403);
    }
}
