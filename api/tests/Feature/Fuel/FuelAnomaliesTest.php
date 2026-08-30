<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-019 (#5813) — Anomalies FuelStation → outbox (notifications).
 *
 * Couvre la détection (relevé anormal, clôture manquante, écart) et la
 * déduplication par (entité, jour) — pas de double notification.
 */
class FuelAnomaliesTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_anomalies_are_detected_and_deduplicated(): void
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

        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'P1',
            'product_types' => ['essence'],
            'status' => 'active',
        ]);

        // Relevé anormal : valeur inférieure au précédent sur le même compteur.
        FuelMeterReading::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_id' => 1,
            'reading_value_minor' => 10000,
            'reading_unit' => 'centiliter',
            'captured_at_utc' => now()->subHours(2),
            'captured_by_employee_id' => 1,
            'source_code' => 'manual',
        ]);
        FuelMeterReading::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_id' => 1,
            'reading_value_minor' => 9500,
            'reading_unit' => 'centiliter',
            'captured_at_utc' => now(),
            'captured_by_employee_id' => 1,
            'source_code' => 'manual',
        ]);

        // Clôture manquante (ouverte depuis > 24 h).
        FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'status' => 'open',
            'opened_at' => now()->subHours(30),
            'opening_balance' => 0,
        ]);

        // Écart de caisse.
        FuelCashSession::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'status' => 'closed',
            'variance' => -500,
            'opened_at' => now()->subHours(5),
            'opening_balance' => 0,
            'closing_balance' => 1000,
            'expected_balance' => 1500,
        ]);

        $this->artisan('leopardo:fuel:anomalies', ['company' => $company->id])
            ->assertSuccessful();

        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.meter.v1')->count());
        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.missing_close.v1')->count());
        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.variance.v1')->count());

        // Rejeu → dédup : aucun doublon.
        $this->artisan('leopardo:fuel:anomalies', ['company' => $company->id])
            ->assertSuccessful();

        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.meter.v1')->count());
        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.missing_close.v1')->count());
        $this->assertSame(1, FuelOutboxEvent::query()->where('event_type', 'fuel.anomaly.variance.v1')->count());
    }
}
