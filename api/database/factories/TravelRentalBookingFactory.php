<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\RentalBookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelRentalBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelRentalBooking>
 */
class TravelRentalBookingFactory extends Factory
{
    protected $model = TravelRentalBooking::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+10 days');
        $end = (clone $start)->modify('+'.$this->faker->numberBetween(1, 5).' days');

        return [
            'vehicle_id' => TravelRentalVehicle::factory(),
            'customer_contact_id' => null,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'total_amount_minor' => $this->faker->numberBetween(50000, 500000),
            'currency' => 'XAF',
            'deposit_amount_minor' => $this->faker->numberBetween(10000, 50000),
            'payment_status' => PaymentStatus::PENDING->value,
            'status' => RentalBookingStatus::PENDING->value,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
