<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantDeliveryRider>
 */
class RestaurantDeliveryRiderFactory extends Factory
{
    protected $model = RestaurantDeliveryRider::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'employee_id' => null,
            'name' => $this->faker->name(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'vehicle_code' => $this->faker->optional()->bothify('VH-####'),
            'is_active' => $this->faker->boolean(85),
        ];
    }
}
