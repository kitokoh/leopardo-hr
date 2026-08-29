<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelBooking>
 */
class TravelBookingFactory extends Factory
{
    protected $model = TravelBooking::class;

    public function definition(): array
    {
        return [
            'trip_id' => TravelTrip::factory(),
            'status' => BookingStatus::PENDING->value,
            'passenger_count' => 1,
            'total_amount_minor' => $this->faker->numberBetween(50000, 500000),
            'currency' => 'XAF',
            'booking_source' => BookingSource::OFFICE->value,
            'customer_contact_id' => null,
            'booked_by_user_id' => null,
            'payment_status' => PaymentStatus::PENDING->value,
            'expires_at' => null,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ];
    }
}
