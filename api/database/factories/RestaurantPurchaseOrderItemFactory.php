<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPurchaseOrderItem>
 */
class RestaurantPurchaseOrderItemFactory extends Factory
{
    protected $model = RestaurantPurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 100);
        $unitPrice = $this->faker->numberBetween(50, 2000);

        return [
            'purchase_order_id' => RestaurantPurchaseOrder::factory(),
            'ingredient_id' => RestaurantIngredient::factory(),
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => (int) round($quantity * $unitPrice),
        ];
    }
}
