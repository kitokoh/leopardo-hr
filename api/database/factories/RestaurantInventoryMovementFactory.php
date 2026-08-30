<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantInventoryMovement>
 */
class RestaurantInventoryMovementFactory extends Factory
{
    protected $model = RestaurantInventoryMovement::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'ingredient_id' => RestaurantIngredient::factory(),
            'stock_level_id' => null,
            'quantity_delta' => $this->faker->randomFloat(3, -50, 50),
            'reason_code' => StockMovementReason::ADJUSTMENT->value,
            'reference_type' => null,
            'reference_id' => null,
            'note_redacted' => $this->faker->optional()->sentence(),
            'user_id' => null,
        ];
    }
}
