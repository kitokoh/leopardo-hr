<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProductIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantProductIngredient>
 */
class RestaurantProductIngredientFactory extends Factory
{
    protected $model = RestaurantProductIngredient::class;

    public function definition(): array
    {
        return [
            'product_id' => RestaurantProduct::factory(),
            'ingredient_id' => RestaurantIngredient::factory(),
            'quantity' => $this->faker->randomFloat(3, 0.05, 2),
            'unit_code' => $this->faker->randomElement(['kg', 'l', 'u', 'pce']),
        ];
    }
}
