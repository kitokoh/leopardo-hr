<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantLoyaltyCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantLoyaltyCustomer>
 */
class RestaurantLoyaltyCustomerFactory extends Factory
{
    protected $model = RestaurantLoyaltyCustomer::class;

    public function definition(): array
    {
        return [
            'customer_contact_id' => $this->faker->unique()->numberBetween(1, 999999),
            'points' => $this->faker->numberBetween(0, 5000),
            'tier_code' => $this->faker->optional()->randomElement(['bronze', 'silver', 'gold']),
        ];
    }
}
