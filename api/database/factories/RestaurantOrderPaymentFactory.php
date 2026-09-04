<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PaymentProvider;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantOrderPayment>
 */
class RestaurantOrderPaymentFactory extends Factory
{
    protected $model = RestaurantOrderPayment::class;

    public function definition(): array
    {
        return [
            'order_id' => RestaurantOrder::factory(),
            'pos_session_id' => null,
            'provider_code' => PaymentProvider::CASH->value,
            'amount_minor' => $this->faker->numberBetween(100, 50000),
            'currency' => 'DZD',
            'status' => PaymentStatus::PENDING->value,
            'paid_at' => null,
            'provider_reference' => $this->faker->optional()->bothify('PAY-##########'),
            'tip_minor' => $this->faker->optional()->numberBetween(50, 500),
            'callback_payload_redacted' => null,
            'idempotency_key' => $this->faker->optional()->uuid(),
        ];
    }
}
