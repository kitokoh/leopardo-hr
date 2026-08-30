<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-015 (#5809) — Contrat Accounting : agrégats validés par outbox.
 *
 * Couvre la publication de `fuel.cash_session.closed.v1` à la clôture
 * (idempotente, agrégats serveur) et le rapprochement idempotent.
 */
class FuelAccountingContractTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_close_publishes_versioned_outbox_event_with_aggregates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-01',
            'name' => 'Station Centre',
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'status' => 'open',
            'opened_at' => now()->subHour(),
            'opening_balance' => 1000,
        ]);

        // Vente rattachée à la session (montant serveur).
        FuelSale::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'cash_session_id' => $session->id,
            'quantity' => 10,
            'unit_price' => 150,
            'amount' => 1500,
            'product' => 'Essence',
            'sale_time' => now(),
            'source' => 'manual',
        ]);

        // Clôture via le service (API réelle).
        $this->actingAsEmployee($company);

        $this->postJson("/api/v1/fuel-station/cash-sessions/{$session->id}/close", ['closing_balance' => 2600])
            ->assertOk();

        $event = FuelOutboxEvent::query()->where('event_type', 'fuel.cash_session.closed.v1')->firstOrFail();

        $this->assertSame('pending', $event->status);
        $this->assertSame('fuel-cash-closed:'.$session->id, $event->idempotency_key);
        $this->assertSame(1500, (int) ($event->payload_redacted['sales_amount'] ?? 0));
        $this->assertSame(100, (int) ($event->payload_redacted['variance'] ?? 0)); // 2600 − (1000+1500)
    }

    public function test_close_event_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-02',
            'name' => 'Station Sud',
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        $session = FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'status' => 'open',
            'opened_at' => now()->subHour(),
            'opening_balance' => 0,
        ]);

        $this->actingAsEmployee($company);

        // Deux clôtures → un seul événement outbox (idempotence).
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$session->id}/close", ['closing_balance' => 500])->assertOk();
        $this->postJson("/api/v1/fuel-station/cash-sessions/{$session->id}/close", ['closing_balance' => 500])->assertOk();

        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.cash_session.closed.v1')->count());
    }

    public function test_reconcile_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        FuelOutboxEvent::query()->create([
            'company_id' => $company->id,
            'event_type' => 'fuel.cash_session.closed.v1',
            'payload_redacted' => ['cash_session_id' => 1],
            'status' => 'pending',
            'attempts' => 0,
            'idempotency_key' => 'fuel-cash-closed:1',
        ]);

        $this->artisan('leopardo:fuel:reconcile', ['company' => $company->id])
            ->assertSuccessful();

        $this->assertSame('published', FuelOutboxEvent::query()->firstOrFail()->status);
        $this->assertSame(1, FuelOutboxEvent::query()->firstOrFail()->attempts);

        // Rejeu → aucun re-traitement (déjà publié).
        $this->artisan('leopardo:fuel:reconcile', ['company' => $company->id])
            ->assertSuccessful();

        $this->assertSame(1, FuelOutboxEvent::query()->firstOrFail()->attempts);
    }

    private function actingAsEmployee(Company $company): void
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($employee);
    }
}
