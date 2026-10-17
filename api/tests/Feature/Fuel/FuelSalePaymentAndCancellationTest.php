<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * P1-a + P1-b (audit FuelStation 2026-10-17) — moyens de paiement et
 * annulation compensatoire des ventes.
 *
 * P1-a : `fuel_sales` ne portait aucune colonne de reglement ; `source`
 * (manual|kiosk|pos) decrit la PROVENANCE de la saisie, pas la NATURE du
 * reglement. Une vente ne pouvait donc pas etre ventilee par mode de paiement.
 *
 * P1-b : aucune action `cancel` n'existait. Le §6 impose qu'une correction
 * N'EFFACE PAS l'historique financier : l'annulation est donc une ECRITURE
 * COMPENSATOIRE (la ligne reste, marquee `cancelled`), jamais un DELETE.
 */
class FuelSalePaymentAndCancellationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0:Company,1:Employee,2:Employee,3:FuelStation}
     */
    private function seedTenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        /** @var Employee $operator */
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
        ]);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-PAY',
            'name' => 'Station paiement',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return [$company, $operator, $manager, $station];
    }

    // ───────────────────────────── P1-a : paiements ─────────────────────────

    public function test_sale_records_payment_method_and_reference(): void
    {
        [, $operator, , $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
            'payment_method' => 'card',
            'payment_reference' => 'TPE-0042',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.payment_reference', 'TPE-0042')
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('fuel_sales', [
            'company_id' => $operator->company_id,
            'payment_method' => 'card',
            'payment_reference' => 'TPE-0042',
        ]);
    }

    public function test_payment_method_defaults_to_cash_when_omitted(): void
    {
        [, $operator, , $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 10,
            'unit_price' => 140,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_method', 'cash');
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        [, $operator, , $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 10,
            'unit_price' => 140,
            'payment_method' => 'crypto',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    // ──────────────────────── P1-b : annulation compensatoire ───────────────

    public function test_manager_cancels_sale_without_deleting_the_row(): void
    {
        [, $operator, $manager, $station] = $this->seedTenant();

        Sanctum::actingAs($operator);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", [
            'reason' => 'Erreur de saisie au comptoir',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Erreur de saisie au comptoir')
            ->assertJsonPath('data.cancelled_by', $manager->id);

        // LA LIGNE EXISTE TOUJOURS : aucun DELETE, l'historique financier est
        // conserve (montant, quantite et operateur restent lisibles).
        $this->assertDatabaseHas('fuel_sales', ['id' => $saleId, 'status' => 'cancelled']);
        $this->assertSame(1, FuelSale::query()->count());

        /** @var FuelSale $sale */
        $sale = FuelSale::query()->findOrFail($saleId);
        $this->assertSame(2800.0, (float) $sale->amount);
        $this->assertSame($operator->id, $sale->employee_id);
        $this->assertNotNull($sale->cancelled_at);
    }

    public function test_cancellation_publishes_outbox_event_for_accounting(): void
    {
        [, $operator, $manager, $station] = $this->seedTenant();

        Sanctum::actingAs($operator);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", [
            'reason' => 'Client parti sans payer',
        ])->assertStatus(200);

        // La comptabilite ne doit jamais rester sur un montant annule.
        $this->assertDatabaseHas('fuel_outbox_events', [
            'company_id' => $operator->company_id,
            'event_type' => FuelOutboxEvent::EVENT_SALE_CANCELLED,
            'aggregate_id' => (string) $saleId,
        ]);
    }

    public function test_operator_cannot_cancel_a_sale(): void
    {
        [, $operator, , $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        // Annuler une piece financiere est un acte de supervision.
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", [
            'reason' => 'Tentative operateur',
        ])->assertStatus(403);

        $this->assertDatabaseHas('fuel_sales', ['id' => $saleId, 'status' => 'completed']);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        [, $operator, $manager, $station] = $this->seedTenant();

        Sanctum::actingAs($operator);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_cancellation_is_refused_on_a_closed_cash_session(): void
    {
        [, $operator, $manager, $station] = $this->seedTenant();

        Sanctum::actingAs($operator);
        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 1000])
            ->assertStatus(201)
            ->json('data.id');

        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
            'cash_session_id' => $sessionId,
        ])->assertStatus(200)->json('data.id');

        // La caisse est comptee et rapprochee : un historique cloture ne se
        // reecrit pas.
        FuelCashSession::query()->where('id', $sessionId)->update(['status' => FuelCashSession::STATUS_CLOSED]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", [
            'reason' => 'Trop tard',
        ])->assertStatus(422);

        $this->assertDatabaseHas('fuel_sales', ['id' => $saleId, 'status' => 'completed']);
    }

    public function test_double_cancellation_is_refused(): void
    {
        [, $operator, $manager, $station] = $this->seedTenant();

        Sanctum::actingAs($operator);
        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", ['reason' => 'Premiere annulation'])
            ->assertStatus(200);

        // Idempotence explicite : pas de double compensation.
        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", ['reason' => 'Seconde annulation'])
            ->assertStatus(422);
    }

    public function test_cross_tenant_cancellation_is_404(): void
    {
        [, $operator, , $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        /** @var int $saleId */
        $saleId = $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
        ])->assertStatus(200)->json('data.id');

        // Manager d'un AUTRE tenant : isolation fail-closed.
        /** @var Company $other */
        $other = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $intruder */
        $intruder = Employee::factory()->create(['company_id' => $other->id, 'role' => 'manager']);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/fuel-station/sales/{$saleId}/cancel", ['reason' => 'Intrusion'])
            ->assertStatus(404);

        $this->assertDatabaseHas('fuel_sales', ['id' => $saleId, 'status' => 'completed']);
    }
}
