<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantIngredient>
 */
class RestaurantIngredientFactory extends Factory
{
    protected $model = RestaurantIngredient::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('ING-###')),
            'name' => $this->faker->unique()->words(2, true),
            'unit_code' => $this->faker->randomElement(['kg', 'l', 'u', 'pce']),
            'avg_cost_minor' => $this->faker->optional()->numberBetween(50, 5000),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
