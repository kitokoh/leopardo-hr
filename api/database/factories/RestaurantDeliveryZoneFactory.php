<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantDeliveryZone>
 */
class RestaurantDeliveryZoneFactory extends Factory
{
    protected $model = RestaurantDeliveryZone::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'name' => $this->faker->unique()->city(),
            'fee_minor' => $this->faker->numberBetween(100, 2000),
            'min_order_minor' => $this->faker->optional()->numberBetween(500, 10000),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
