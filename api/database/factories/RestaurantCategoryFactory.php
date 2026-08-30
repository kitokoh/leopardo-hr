<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantCategory>
 */
class RestaurantCategoryFactory extends Factory
{
    protected $model = RestaurantCategory::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => $this->faker->unique()->words(2, true),
            'color' => $this->faker->optional()->hexColor(),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
