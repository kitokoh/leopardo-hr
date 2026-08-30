<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RefundStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantRefund>
 */
class RestaurantRefundFactory extends Factory
{
    protected $model = RestaurantRefund::class;

    public function definition(): array
    {
        return [
            'order_id' => RestaurantOrder::factory(),
            'payment_id' => null,
            'amount_minor' => $this->faker->numberBetween(100, 20000),
            'reason_code' => $this->faker->randomElement(['customer_request', 'duplicate_charge', 'wrong_amount', 'cancelled_order']),
            'reason_text_redacted' => $this->faker->optional()->sentence(),
            'refunded_by_user_id' => null,
            'status' => RefundStatus::PENDING->value,
            'idempotency_key' => $this->faker->optional()->uuid(),
        ];
    }
}
