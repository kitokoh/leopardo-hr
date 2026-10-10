<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * VTC-02 (#8358) — Harness de test BC-34 : schéma tenant complet.
 *
 * Garantit que les 6 tables du module Vtc sont créées par le runner de
 * migrations tenant (`leopardo:migrate`) et donc disponibles dans tous les
 * tests Feature utilisant `RefreshTenantDatabase` (parité CreatesMvpSchema
 * #5443 maintenue dans api/tests/Support/CreatesMvpSchema.php).
 */
class VtcSchemaTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<int, list<string>>
     */
    public static function vtcTables(): array
    {
        // PHPUnit 11+ : chaque entrée du data provider doit être un tableau
        // d'arguments (une simple liste de strings est invalide).
        return [
            ['vtc_vehicles'],
            ['vtc_drivers'],
            ['vtc_fare_profiles'],
            ['vtc_rides'],
            ['vtc_ride_events'],
            ['vtc_driver_positions'],
        ];
    }

    /**
     * @dataProvider vtcTables
     */
    public function test_vtc_table_exists(string $table): void
    {
        self::assertTrue(Schema::hasTable($table), sprintf('Table "%s" manquante (leopardo:migrate).', $table));
    }

    public function test_vtc_rides_has_tenant_idempotency_and_cycle_columns(): void
    {
        $columns = Schema::getColumnListing('vtc_rides');

        foreach ([
            'company_id', 'reference', 'passenger_user_id', 'passenger_name',
            'pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude',
            'status', 'fare_profile_id', 'estimated_distance_m', 'estimated_duration_s',
            'estimated_price_minor', 'final_price_minor', 'currency', 'driver_id',
            'requested_at', 'accepted_at', 'arrived_at', 'started_at', 'completed_at',
            'cancelled_at', 'expired_at', 'cancel_reason', 'idempotency_key', 'metadata',
        ] as $column) {
            self::assertContains($column, $columns, sprintf('Colonne "%s" manquante sur vtc_rides.', $column));
        }

        self::assertTrue(Schema::hasIndex('vtc_rides', 'vtc_rides_company_reference_unique'));
        self::assertTrue(Schema::hasIndex('vtc_rides', 'vtc_rides_company_idempotency_unique'));
        self::assertTrue(Schema::hasIndex('vtc_rides', 'vtc_rides_company_status_date_idx'));
    }

    public function test_vtc_drivers_has_dispatch_columns(): void
    {
        $columns = Schema::getColumnListing('vtc_drivers');

        foreach (['company_id', 'user_id', 'name', 'status', 'vehicle_id', 'current_latitude', 'current_longitude', 'location_updated_at'] as $column) {
            self::assertContains($column, $columns, sprintf('Colonne "%s" manquante sur vtc_drivers.', $column));
        }

        self::assertTrue(Schema::hasIndex('vtc_drivers', 'vtc_drivers_company_status_idx'));
    }

    public function test_vtc_driver_positions_has_idempotent_unique_index(): void
    {
        self::assertTrue(Schema::hasIndex('vtc_driver_positions', 'vtc_driver_positions_company_driver_at_unique'));
    }

    public function test_vtc_vehicles_has_tenant_unique_plate(): void
    {
        self::assertTrue(Schema::hasIndex('vtc_vehicles', 'vtc_vehicles_company_plate_unique'));
    }

    public function test_vtc_ride_events_is_append_only_shaped(): void
    {
        $columns = Schema::getColumnListing('vtc_ride_events');

        self::assertContains('created_at', $columns);
        // Journal append-only : aucune colonne updated_at.
        self::assertNotContains('updated_at', $columns);
    }
}
