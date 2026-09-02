<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\PaymentProvider;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelPayment>
 */
class TravelPaymentFactory extends Factory
{
    protected $model = TravelPayment::class;

    public function definition(): array
    {
        return [
            'booking_id' => TravelBooking::factory(),
            'provider_code' => PaymentProvider::CASH->value,
            'amount_minor' => $this->faker->numberBetween(50000, 500000),
            'currency' => 'XAF',
            'status' => PaymentStatus::PENDING->value,
            'provider_reference' => null,
            'callback_payload_redacted' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
