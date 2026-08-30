<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantZone>
 */
class RestaurantZoneFactory extends Factory
{
    protected $model = RestaurantZone::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'name' => $this->faker->unique()->city(),
            'color' => $this->faker->optional()->hexColor(),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
