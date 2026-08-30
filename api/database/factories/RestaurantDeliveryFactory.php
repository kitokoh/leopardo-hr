<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantDelivery>
 */
class RestaurantDeliveryFactory extends Factory
{
    protected $model = RestaurantDelivery::class;

    public function definition(): array
    {
        return [
            'order_id' => RestaurantOrder::factory(),
            'zone_id' => null,
            'rider_id' => null,
            'status' => DeliveryStatus::PENDING->value,
            'fee_minor' => $this->faker->numberBetween(100, 1500),
            'delivered_at' => null,
            'delivered_to_contact' => $this->faker->optional()->name(),
        ];
    }
}
