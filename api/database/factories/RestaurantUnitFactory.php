<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantUnit>
 */
class RestaurantUnitFactory extends Factory
{
    protected $model = RestaurantUnit::class;

    public function definition(): array
    {
        return [
            'code' => strtolower($this->faker->unique()->bothify('??##')),
            'label' => $this->faker->unique()->word(),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
