<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\LoyaltyPointsReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyPointsMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantLoyaltyPointsMovement>
 */
class RestaurantLoyaltyPointsMovementFactory extends Factory
{
    protected $model = RestaurantLoyaltyPointsMovement::class;

    public function definition(): array
    {
        return [
            'customer_id' => RestaurantLoyaltyCustomer::factory(),
            'delta' => $this->faker->numberBetween(-200, 500),
            'reason_code' => LoyaltyPointsReason::EARN->value,
            'order_id' => null,
            'reference_id' => null,
        ];
    }
}
