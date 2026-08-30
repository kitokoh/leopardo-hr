<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantInventoryCountItem>
 */
class RestaurantInventoryCountItemFactory extends Factory
{
    protected $model = RestaurantInventoryCountItem::class;

    public function definition(): array
    {
        $expected = $this->faker->randomFloat(3, 0, 100);
        $counted = $this->faker->randomFloat(3, 0, 100);

        return [
            'count_id' => RestaurantInventoryCount::factory(),
            'ingredient_id' => RestaurantIngredient::factory(),
            'expected_qty' => $expected,
            'counted_qty' => $counted,
            'variance_qty' => round($counted - $expected, 3),
            'reason_code' => null,
        ];
    }
}
