<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Application\Services\CogsCalculator;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-506 (#6205) — COGS : calcul serveur à la clôture
 * (quantités × composition × coût moyen).
 *
 * Couvre : calcul exact (critère d'acceptation) et reprise idempotente
 * (« COGS recalculable = même résultat ») — fonction pure, sans écriture.
 */
class RestaurantCogsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_cogs_is_computed_exactly_and_idempotently(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $context = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $branch = RestaurantBranch::factory()->create();

            $ingredientA = RestaurantIngredient::factory()->create(); // coût 1000
            $ingredientB = RestaurantIngredient::factory()->create(); // coût 500

            $product = RestaurantProduct::factory()->create([
                'branch_id' => $branch->id,
                'price_minor' => 3000,
                'currency' => $branch->currency,
                'is_available' => true,
            ]);

            // 1 produit = 0.4 kg de A + 0.2 kg de B.
            RestaurantProductIngredient::query()->create(['company_id' => $company->id, 'product_id' => $product->id, 'ingredient_id' => $ingredientA->id, 'quantity' => 0.4, 'unit_code' => 'kg']);
            RestaurantProductIngredient::query()->create(['company_id' => $company->id, 'product_id' => $product->id, 'ingredient_id' => $ingredientB->id, 'quantity' => 0.2, 'unit_code' => 'kg']);

            RestaurantStockLevel::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'ingredient_id' => $ingredientA->id, 'quantity' => 10, 'avg_cost_minor' => 1000]);
            RestaurantStockLevel::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'ingredient_id' => $ingredientB->id, 'quantity' => 10, 'avg_cost_minor' => 500]);

            $session = RestaurantPosSession::factory()->create([
                'branch_id' => $branch->id,
                'status' => 'open',
                'opened_by_user_id' => 1,
            ]);

            $order = RestaurantOrder::factory()->create([
                'branch_id' => $branch->id,
                'pos_session_id' => $session->id,
                'status' => 'paid',
                'currency' => $branch->currency,
            ]);

            // 3 produits vendus.
            RestaurantOrderItem::query()->create([
                'company_id' => $company->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_price_minor' => 3000,
                'line_total_minor' => 9000,
                'tax_minor' => 0,
                'status' => 'active',
                'line_index' => 1,
            ]);

            return ['session' => $session];
        });

        // COGS attendu : 3 × (0.4×1000 + 0.2×500) = 3 × 500 = 1500.
        $calculator = app(CogsCalculator::class);

        $first = app(TenantManager::class)->withinTenant($company, fn (): int => $calculator->calculateForSession($context['session']));
        $second = app(TenantManager::class)->withinTenant($company, fn (): int => $calculator->calculateForSession($context['session']));

        $this->assertSame(1500, $first);
        $this->assertSame($first, $second); // reprise idempotente : même résultat
    }
}
