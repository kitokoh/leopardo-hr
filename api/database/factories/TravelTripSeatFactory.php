<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\SeatStatus;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTripSeat>
 */
class TravelTripSeatFactory extends Factory
{
    protected $model = TravelTripSeat::class;

    public function definition(): array
    {
        return [
            'trip_id' => TravelTrip::factory(),
            'seat_number' => $this->faker->unique()->numberBetween(1, 1000),
            'status' => SeatStatus::FREE->value,
            'booking_id' => null,
            'passenger_id' => null,
            'reserved_until' => null,
        ];
    }
}
