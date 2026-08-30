<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantOrderItem>
 */
class RestaurantOrderItemFactory extends Factory
{
    protected $model = RestaurantOrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 5);
        $unitPrice = $this->faker->numberBetween(100, 5000);

        return [
            'order_id' => RestaurantOrder::factory(),
            'product_id' => RestaurantProduct::factory(),
            'menu_id' => null,
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => (int) round($quantity * $unitPrice),
            'tax_rate_id' => RestaurantTaxRate::factory(),
            'tax_minor' => null,
            'status' => OrderItemStatus::ACTIVE->value,
            'line_index' => $this->faker->numberBetween(0, 30),
        ];
    }
}
