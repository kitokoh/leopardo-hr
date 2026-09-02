<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantHour>
 */
class RestaurantHourFactory extends Factory
{
    protected $model = RestaurantHour::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'opens_at' => '09:00:00',
            'closes_at' => '22:00:00',
            'is_closed' => $this->faker->boolean(10),
        ];
    }
}
