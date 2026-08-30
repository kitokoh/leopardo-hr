<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-108 (#6165) — Harness de test BC-25 : schéma tenant complet.
 *
 * Garantit que les 35 tables de la verticale RestaurantManager sont créées
 * par le runner de migrations tenant (`leopardo:migrate`) et donc disponibles
 * dans tous les tests Feature utilisant `RefreshTenantDatabase` (parité
 * CreatesMvpSchema #5443 maintenue dans api/tests/Support/CreatesMvpSchema.php).
 */
class RestaurantSchemaTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<int, string>
     */
    public static function restaurantTables(): array
    {
        return [
            'restaurant_branches',
            'restaurant_zones',
            'restaurant_tables',
            'restaurant_categories',
            'restaurant_products',
            'restaurant_product_ingredients',
            'restaurant_ingredients',
            'restaurant_units',
            'restaurant_tax_rates',
            'restaurant_menus',
            'restaurant_menu_items',
            'restaurant_hours',
            'restaurant_suppliers',
            'restaurant_stock_levels',
            'restaurant_inventory_movements',
            'restaurant_purchase_orders',
            'restaurant_purchase_order_items',
            'restaurant_receivings',
            'restaurant_inventory_counts',
            'restaurant_inventory_count_items',
            'restaurant_pos_sessions',
            'restaurant_orders',
            'restaurant_order_items',
            'restaurant_order_payments',
            'restaurant_refunds',
            'restaurant_table_sessions',
            'restaurant_reservations',
            'restaurant_delivery_zones',
            'restaurant_delivery_riders',
            'restaurant_deliveries',
            'restaurant_loyalty_programs',
            'restaurant_loyalty_customers',
            'restaurant_loyalty_points_movements',
            'restaurant_promotions',
            'restaurant_outbox_events',
        ];
    }

    public function test_all_restaurant_tables_exist_in_tenant_schema(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $missing = app(TenantManager::class)->withinTenant($company, function () {
            $missing = [];

            foreach (self::restaurantTables() as $table) {
                if (! Schema::hasTable($table)) {
                    $missing[] = $table;
                }
            }

            return $missing;
        });

        $this->assertSame([], $missing, 'Tables restaurant manquantes dans le schéma tenant.');
    }

    public function test_restaurant_tables_are_tenant_scoped_with_company_id(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $unscoped = app(TenantManager::class)->withinTenant($company, function () {
            $unscoped = [];

            foreach (self::restaurantTables() as $table) {
                $columns = Schema::getColumnListing($table);

                if (! in_array('company_id', $columns, true)) {
                    $unscoped[] = $table;
                }
            }

            return $unscoped;
        });

        $this->assertSame([], $unscoped, 'Tables restaurant sans colonne company_id (violation tenant-safe).');
    }
}
