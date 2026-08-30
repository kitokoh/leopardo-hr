<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Enums\PaymentProvider;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seed de données démonstratives pour la verticale RestaurantManager
 * (RESTO-107, issue #6164).
 *
 * Génère un jeu de données synthétiques NON SENSIBLES pour un tenant pilote :
 *   - référentiel (via RestaurantReferentialSeederService) ;
 *   - une branche de démonstration `DEMO` avec zones (Salle, Terrasse),
 *     tables (T1..T8), produits + compositions, ingrédients + stocks, un
 *     menu (Menu Midi) et une session de caisse fermée avec une commande
 *     soldée (dates passées).
 *
 * Idempotence : les insertions utilisent insertOrIgnore sur les clés
 * uniques tenant-scoped (avec vérification d'existence explicite quand la
 * clé comporte une colonne NULLable) ; le bloc caisse (session + commande +
 * articles + paiement) est ancré sur la référence de commande
 * `RST-DEMO0001` — rejouer le seed ne crée jamais de doublon et laisse
 * l'état identique.
 */
final class RestaurantDemoSeederService
{
    /** Référence de la commande de démonstration (ancre d'idempotence). */
    private const DEMO_ORDER_REFERENCE = 'RST-DEMO0001';

    /** Clés d'idempotence fixes (UUID v4) du bloc caisse démo. */
    private const DEMO_ORDER_IDEMPOTENCY_KEY = '00000000-0000-4000-8000-000000000001';

    private const DEMO_PAYMENT_IDEMPOTENCY_KEY = '00000000-0000-4000-8000-000000000002';

    public function __construct(
        private readonly RestaurantReferentialSeederService $referentialSeeder,
        private readonly TenantManager $tenants,
    ) {}

    public function seed(Company $company): void
    {
        $this->referentialSeeder->seed($company);

        $this->tenants->withinTenant($company, function () use ($company): void {
            $this->seedDemoBranch($company);
            $this->seedZonesAndTables($company);
            $this->seedIngredientsAndStockLevels($company);
            $this->seedProducts($company);
            $this->seedMenu($company);
            $this->seedDemoPosBlock($company);
        });
    }

    /**
     * Convertit un identifiant lu en base (int ou chaîne numérique) en
     * entier, ou null si la valeur est absente ou invalide.
     */
    private function toId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return intval($value);
    }

    private function seedDemoBranch(Company $company): void
    {
        DB::table('restaurant_branches')->insertOrIgnore([
            'company_id' => $company->id,
            'code' => 'DEMO',
            'name' => 'Branche DEMO',
            'address' => null,
            'city' => null,
            'phone' => null,
            'timezone' => $company->timezone ?: 'UTC',
            'currency' => $company->currency ?: 'DZD',
            'status' => RestaurantRecordStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function branchId(Company $company, string $code): ?int
    {
        return $this->toId(
            DB::table('restaurant_branches')
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->value('id'),
        );
    }

    private function seedZonesAndTables(Company $company): void
    {
        $branchId = $this->branchId($company, 'DEMO');
        if ($branchId === null) {
            return;
        }

        $zones = [
            ['name' => 'Salle', 'sort_order' => 1],
            ['name' => 'Terrasse', 'sort_order' => 2],
        ];

        $zoneRows = [];
        foreach ($zones as $zone) {
            $zoneRows[] = [
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'name' => $zone['name'],
                'color' => null,
                'sort_order' => $zone['sort_order'],
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_zones')->insertOrIgnore($zoneRows);

        /** @var Collection<string, mixed> $zoneIds */
        $zoneIds = DB::table('restaurant_zones')
            ->where('company_id', $company->id)
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->pluck('id', 'name');

        $tables = [
            ['label' => 'T1', 'zone' => 'Salle', 'capacity' => 2],
            ['label' => 'T2', 'zone' => 'Salle', 'capacity' => 2],
            ['label' => 'T3', 'zone' => 'Salle', 'capacity' => 2],
            ['label' => 'T4', 'zone' => 'Salle', 'capacity' => 4],
            ['label' => 'T5', 'zone' => 'Salle', 'capacity' => 4],
            ['label' => 'T6', 'zone' => 'Salle', 'capacity' => 4],
            ['label' => 'T7', 'zone' => 'Terrasse', 'capacity' => 6],
            ['label' => 'T8', 'zone' => 'Terrasse', 'capacity' => 6],
        ];

        $tableRows = [];
        foreach ($tables as $table) {
            $zoneId = $this->toId($zoneIds[$table['zone']] ?? null);

            $tableRows[] = [
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'zone_id' => $zoneId,
                'label' => $table['label'],
                'capacity' => $table['capacity'],
                'min_covers' => null,
                'is_mergeable' => false,
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_tables')->insertOrIgnore($tableRows);
    }

    private function seedIngredientsAndStockLevels(Company $company): void
    {
        $branchId = $this->branchId($company, 'DEMO');
        if ($branchId === null) {
            return;
        }

        $ingredients = [
            ['code' => 'DEMO-FARINE', 'name' => 'Farine', 'unit_code' => 'kg', 'avg_cost_minor' => 120, 'stock' => 20.0, 'reorder' => 5.0, 'alert' => 2.0],
            ['code' => 'DEMO-MOZZA', 'name' => 'Mozzarella', 'unit_code' => 'kg', 'avg_cost_minor' => 800, 'stock' => 5.0, 'reorder' => 2.0, 'alert' => 1.0],
            ['code' => 'DEMO-SAUCE', 'name' => 'Sauce tomate', 'unit_code' => 'l', 'avg_cost_minor' => 300, 'stock' => 8.0, 'reorder' => 2.0, 'alert' => 1.0],
            ['code' => 'DEMO-BOEUF', 'name' => 'Boeuf hache', 'unit_code' => 'kg', 'avg_cost_minor' => 1200, 'stock' => 10.0, 'reorder' => 3.0, 'alert' => 1.0],
            ['code' => 'DEMO-PAIN', 'name' => 'Pain burger', 'unit_code' => 'u', 'avg_cost_minor' => 50, 'stock' => 30.0, 'reorder' => 10.0, 'alert' => 5.0],
            ['code' => 'DEMO-COCA', 'name' => 'Coca', 'unit_code' => 'u', 'avg_cost_minor' => 80, 'stock' => 48.0, 'reorder' => 12.0, 'alert' => 6.0],
            ['code' => 'DEMO-EAU', 'name' => 'Eau', 'unit_code' => 'u', 'avg_cost_minor' => 30, 'stock' => 24.0, 'reorder' => 12.0, 'alert' => 6.0],
        ];

        foreach ($ingredients as $ingredient) {
            $exists = DB::table('restaurant_ingredients')
                ->where('company_id', $company->id)
                ->whereNull('branch_id')
                ->where('code', $ingredient['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('restaurant_ingredients')->insert([
                'company_id' => $company->id,
                'branch_id' => null,
                'code' => $ingredient['code'],
                'name' => $ingredient['name'],
                'unit_code' => $ingredient['unit_code'],
                'avg_cost_minor' => $ingredient['avg_cost_minor'],
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        /** @var Collection<string, mixed> $ingredientIds */
        $ingredientIds = DB::table('restaurant_ingredients')
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->whereIn('code', array_column($ingredients, 'code'))
            ->pluck('id', 'code');

        $stockRows = [];
        foreach ($ingredients as $ingredient) {
            $ingredientId = $this->toId($ingredientIds[$ingredient['code']] ?? null);
            if ($ingredientId === null) {
                continue;
            }

            $stockRows[] = [
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'quantity' => $ingredient['stock'],
                'avg_cost_minor' => $ingredient['avg_cost_minor'],
                'reorder_level' => $ingredient['reorder'],
                'alert_threshold' => $ingredient['alert'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_stock_levels')->insertOrIgnore($stockRows);
    }

    private function seedProducts(Company $company): void
    {
        /** @var Collection<string, mixed> $categoryIds */
        $categoryIds = DB::table('restaurant_categories')
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->pluck('id', 'name');

        /** @var Collection<string, mixed> $taxRateIds */
        $taxRateIds = DB::table('restaurant_tax_rates')
            ->where('company_id', $company->id)
            ->pluck('id', 'code');

        $products = [
            ['code' => 'DEMO-PIZZA-MARG', 'name' => 'Pizza Margherita', 'category' => 'Plats', 'price_minor' => 1200, 'cost_minor' => 190],
            ['code' => 'DEMO-BURGER', 'name' => 'Burger', 'category' => 'Plats', 'price_minor' => 950, 'cost_minor' => 230],
            ['code' => 'DEMO-COCA', 'name' => 'Coca', 'category' => 'Boissons', 'price_minor' => 300, 'cost_minor' => 80],
            ['code' => 'DEMO-EAU', 'name' => 'Eau', 'category' => 'Boissons', 'price_minor' => 150, 'cost_minor' => 30],
        ];

        $productRows = [];
        foreach ($products as $product) {
            $categoryId = $this->toId($categoryIds[$product['category']] ?? null);
            if ($categoryId === null) {
                continue;
            }

            $taxRateId = $this->toId($taxRateIds['TVA19'] ?? null);

            $productRows[] = [
                'company_id' => $company->id,
                'branch_id' => null,
                'category_id' => $categoryId,
                'code' => $product['code'],
                'name' => $product['name'],
                'description_redacted' => null,
                'price_minor' => $product['price_minor'],
                'currency' => $company->currency ?: 'DZD',
                'cost_minor' => $product['cost_minor'],
                'tax_rate_id' => $taxRateId,
                'is_available' => true,
                'image_asset_id' => null,
                'status' => RestaurantRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_products')->insertOrIgnore($productRows);

        $this->seedProductIngredients($company, $products);
    }

    /**
     * Compositions (1-2 ingrédients par produit) — idempotentes via
     * UNIQUE(company_id, product_id, ingredient_id).
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    private function seedProductIngredients(Company $company, array $products): void
    {
        /** @var Collection<string, mixed> $productIds */
        $productIds = DB::table('restaurant_products')
            ->where('company_id', $company->id)
            ->whereIn('code', array_column($products, 'code'))
            ->pluck('id', 'code');

        /** @var Collection<string, mixed> $ingredientIds */
        $ingredientIds = DB::table('restaurant_ingredients')
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->where('code', 'like', 'DEMO-%')
            ->pluck('id', 'code');

        $compositions = [
            'DEMO-PIZZA-MARG' => [
                ['ingredient' => 'DEMO-FARINE', 'quantity' => 0.300, 'unit_code' => 'kg'],
                ['ingredient' => 'DEMO-SAUCE', 'quantity' => 0.100, 'unit_code' => 'l'],
                ['ingredient' => 'DEMO-MOZZA', 'quantity' => 0.150, 'unit_code' => 'kg'],
            ],
            'DEMO-BURGER' => [
                ['ingredient' => 'DEMO-BOEUF', 'quantity' => 0.150, 'unit_code' => 'kg'],
                ['ingredient' => 'DEMO-PAIN', 'quantity' => 1.000, 'unit_code' => 'u'],
            ],
            'DEMO-COCA' => [
                ['ingredient' => 'DEMO-COCA', 'quantity' => 1.000, 'unit_code' => 'u'],
            ],
            'DEMO-EAU' => [
                ['ingredient' => 'DEMO-EAU', 'quantity' => 1.000, 'unit_code' => 'u'],
            ],
        ];

        $rows = [];
        foreach ($compositions as $productCode => $items) {
            $productId = $this->toId($productIds[$productCode] ?? null);
            if ($productId === null) {
                continue;
            }

            foreach ($items as $item) {
                $ingredientId = $this->toId($ingredientIds[$item['ingredient']] ?? null);
                if ($ingredientId === null) {
                    continue;
                }

                $rows[] = [
                    'company_id' => $company->id,
                    'product_id' => $productId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => $item['quantity'],
                    'unit_code' => $item['unit_code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('restaurant_product_ingredients')->insertOrIgnore($rows);
    }

    private function seedMenu(Company $company): void
    {
        DB::table('restaurant_menus')->insertOrIgnore([
            'company_id' => $company->id,
            'branch_id' => null,
            'code' => 'DEMO-MENU-MIDI',
            'name' => 'Menu Midi',
            'price_minor' => 1500,
            'currency' => $company->currency ?: 'DZD',
            'starts_at' => null,
            'ends_at' => null,
            'status' => RestaurantRecordStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = $this->toId(
            DB::table('restaurant_menus')
                ->where('company_id', $company->id)
                ->where('code', 'DEMO-MENU-MIDI')
                ->value('id'),
        );

        if ($menuId === null) {
            return;
        }

        /** @var Collection<string, mixed> $productIds */
        $productIds = DB::table('restaurant_products')
            ->where('company_id', $company->id)
            ->whereIn('code', ['DEMO-PIZZA-MARG', 'DEMO-BURGER', 'DEMO-COCA'])
            ->pluck('id', 'code');

        $items = [
            ['product' => 'DEMO-PIZZA-MARG', 'position' => 1, 'optional' => false],
            ['product' => 'DEMO-BURGER', 'position' => 2, 'optional' => false],
            ['product' => 'DEMO-COCA', 'position' => 3, 'optional' => true],
        ];

        $rows = [];
        foreach ($items as $item) {
            $productId = $this->toId($productIds[$item['product']] ?? null);
            if ($productId === null) {
                continue;
            }

            $rows[] = [
                'company_id' => $company->id,
                'menu_id' => $menuId,
                'product_id' => $productId,
                'position' => $item['position'],
                'is_optional' => $item['optional'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('restaurant_menu_items')->insertOrIgnore($rows);
    }

    /**
     * Bloc caisse de démonstration : une session POS fermée avec une commande
     * soldée (dates passées). Ancrée sur la référence de commande pour
     * l'idempotence — le bloc n'est inséré qu'une seule fois.
     */
    private function seedDemoPosBlock(Company $company): void
    {
        $orderExists = DB::table('restaurant_orders')
            ->where('company_id', $company->id)
            ->where('reference', self::DEMO_ORDER_REFERENCE)
            ->exists();

        if ($orderExists) {
            return;
        }

        $branchId = $this->branchId($company, 'DEMO');
        if ($branchId === null) {
            return;
        }

        DB::transaction(function () use ($company, $branchId): void {
            $openedAt = now()->subDays(7)->setTime(11, 0, 0);
            $closedAt = now()->subDays(7)->setTime(14, 30, 0);

            /** @var Collection<string, mixed> $productIds */
            $productIds = DB::table('restaurant_products')
                ->where('company_id', $company->id)
                ->whereIn('code', ['DEMO-PIZZA-MARG', 'DEMO-COCA'])
                ->pluck('id', 'code');

            $taxRateId = $this->toId(
                DB::table('restaurant_tax_rates')
                    ->where('company_id', $company->id)
                    ->where('code', 'TVA19')
                    ->value('id'),
            );

            $tableId = $this->toId(
                DB::table('restaurant_tables')
                    ->where('company_id', $company->id)
                    ->where('branch_id', $branchId)
                    ->where('label', 'T1')
                    ->value('id'),
            );

            $zoneId = $this->toId(
                DB::table('restaurant_zones')
                    ->where('company_id', $company->id)
                    ->where('branch_id', $branchId)
                    ->where('name', 'Salle')
                    ->value('id'),
            );

            $sessionId = DB::table('restaurant_pos_sessions')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'opened_by_user_id' => 1,
                'closed_by_user_id' => 1,
                'opening_cash_minor' => 5000,
                'expected_cash_minor' => 8213,
                'counted_cash_minor' => 8213,
                'variance_minor' => 0,
                'variance_reason' => null,
                'status' => PosSessionStatus::CLOSED->value,
                'version' => 1,
                'created_at' => $openedAt,
                'updated_at' => $closedAt,
            ]);

            $orderId = DB::table('restaurant_orders')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'pos_session_id' => $sessionId,
                'reference' => self::DEMO_ORDER_REFERENCE,
                'order_type' => OrderType::DINE_IN->value,
                'table_id' => $tableId,
                'zone_id' => $zoneId,
                'covers' => 2,
                'customer_contact_id' => null,
                'rider_id' => null,
                'status' => OrderStatus::CLOSED->value,
                'subtotal_minor' => 2700,
                'tax_minor' => 513,
                'discount_minor' => 0,
                'total_minor' => 3213,
                'currency' => $company->currency ?: 'DZD',
                'source' => OrderSource::POS->value,
                'note_redacted' => null,
                'idempotency_key' => self::DEMO_ORDER_IDEMPOTENCY_KEY,
                'version' => 1,
                'created_at' => $openedAt,
                'updated_at' => $closedAt,
            ]);

            $pizzaId = $this->toId($productIds['DEMO-PIZZA-MARG'] ?? null);
            $cocaId = $this->toId($productIds['DEMO-COCA'] ?? null);

            $itemRows = [];
            if ($pizzaId !== null) {
                $itemRows[] = [
                    'company_id' => $company->id,
                    'order_id' => $orderId,
                    'product_id' => $pizzaId,
                    'menu_id' => null,
                    'quantity' => 2.0,
                    'unit_price_minor' => 1200,
                    'line_total_minor' => 2400,
                    'tax_rate_id' => $taxRateId,
                    'tax_minor' => 456,
                    'status' => OrderItemStatus::ACTIVE->value,
                    'line_index' => 0,
                    'created_at' => $openedAt,
                    'updated_at' => $openedAt,
                ];
            }

            if ($cocaId !== null) {
                $itemRows[] = [
                    'company_id' => $company->id,
                    'order_id' => $orderId,
                    'product_id' => $cocaId,
                    'menu_id' => null,
                    'quantity' => 1.0,
                    'unit_price_minor' => 300,
                    'line_total_minor' => 300,
                    'tax_rate_id' => $taxRateId,
                    'tax_minor' => 57,
                    'status' => OrderItemStatus::ACTIVE->value,
                    'line_index' => 1,
                    'created_at' => $openedAt,
                    'updated_at' => $openedAt,
                ];
            }

            foreach (array_chunk($itemRows, 50) as $chunk) {
                DB::table('restaurant_order_items')->insert($chunk);
            }

            DB::table('restaurant_order_payments')->insert([
                'company_id' => $company->id,
                'order_id' => $orderId,
                'pos_session_id' => $sessionId,
                'provider_code' => PaymentProvider::CASH->value,
                'amount_minor' => 3213,
                'currency' => $company->currency ?: 'DZD',
                'status' => PaymentStatus::CONFIRMED->value,
                'paid_at' => $closedAt,
                'provider_reference' => null,
                'tip_minor' => null,
                'callback_payload_redacted' => null,
                'idempotency_key' => self::DEMO_PAYMENT_IDEMPOTENCY_KEY,
                'created_at' => $closedAt,
                'updated_at' => $closedAt,
            ]);
        });
    }
}
