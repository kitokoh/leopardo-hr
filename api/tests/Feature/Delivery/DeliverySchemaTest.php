<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-102 (#6283) — Harness de test BC-26 : schéma tenant complet.
 *
<<<<<<< HEAD
 * Garantit que les 5 tables du module Delivery sont créées par le runner de
=======
 * Garantit que les 6 tables du module Delivery sont créées par le runner de
>>>>>>> origin/feat/delivery-205-cod
 * migrations tenant (`leopardo:migrate`) et donc disponibles dans tous les
 * tests Feature utilisant `RefreshTenantDatabase` (parité CreatesMvpSchema
 * #5443 maintenue dans api/tests/Support/CreatesMvpSchema.php).
 */
class DeliverySchemaTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
<<<<<<< HEAD
     * @return array<int, list<string>>
=======
     * @return array<int, string>
>>>>>>> origin/feat/delivery-205-cod
     */
    public static function deliveryTables(): array
    {
        return [
<<<<<<< HEAD
            ['delivery_deliveries'],
            ['delivery_routes'],
            ['delivery_stops'],
            ['delivery_events'],
            ['delivery_cod_settlements'],
=======
            'delivery_deliveries',
            'delivery_routes',
            'delivery_stops',
            'delivery_events',
            'delivery_cod_settlements',
            'delivery_tracking_shares',
>>>>>>> origin/feat/delivery-205-cod
        ];
    }

    /**
     * @dataProvider deliveryTables
     */
    public function test_delivery_table_exists(string $table): void
    {
        self::assertTrue(Schema::hasTable($table), sprintf('Table "%s" manquante (leopardo:migrate).', $table));
    }

    public function test_delivery_deliveries_has_tenant_and_idempotency_columns(): void
    {
        $columns = Schema::getColumnListing('delivery_deliveries');

        foreach (['company_id', 'reference', 'source', 'source_reference', 'status', 'cod_amount_minor', 'idempotency_key'] as $column) {
            self::assertContains($column, $columns, sprintf('Colonne "%s" manquante sur delivery_deliveries.', $column));
        }

        self::assertTrue(Schema::hasIndex('delivery_deliveries', 'delivery_deliveries_company_reference_unique'));
        self::assertTrue(Schema::hasIndex('delivery_deliveries', 'delivery_deliveries_company_source_ref_unique'));
        self::assertTrue(Schema::hasIndex('delivery_deliveries', 'delivery_deliveries_company_status_date_idx'));
    }

    public function test_delivery_events_has_idempotency_unique_index(): void
    {
        self::assertTrue(Schema::hasIndex('delivery_events', 'delivery_events_company_delivery_type_at_unique'));
    }
}
