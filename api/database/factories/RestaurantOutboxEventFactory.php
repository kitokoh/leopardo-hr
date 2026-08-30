<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantOutboxEvent>
 */
class RestaurantOutboxEventFactory extends Factory
{
    protected $model = RestaurantOutboxEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => $this->faker->randomElement([
                'restaurant.order.created.v1',
                'restaurant.order.paid.v1',
                'restaurant.payment.confirmed.v1',
                'restaurant.pos.closed.v1',
            ]),
            'payload_redacted' => null,
            'status' => 'pending',
            'available_at' => now(),
            'attempts' => 0,
            'last_error' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
