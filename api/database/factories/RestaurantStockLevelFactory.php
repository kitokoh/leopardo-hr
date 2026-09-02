<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantStockLevel>
 */
class RestaurantStockLevelFactory extends Factory
{
    protected $model = RestaurantStockLevel::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'ingredient_id' => RestaurantIngredient::factory(),
            'quantity' => $this->faker->randomFloat(3, 0, 200),
            'avg_cost_minor' => $this->faker->optional()->numberBetween(10, 2000),
            'reorder_level' => $this->faker->optional()->randomFloat(3, 0, 50),
            'alert_threshold' => $this->faker->optional()->randomFloat(3, 0, 20),
        ];
    }
}
