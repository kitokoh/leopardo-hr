<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\MeansOfTransport;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTrip>
 */
class TravelTripFactory extends Factory
{
    protected $model = TravelTrip::class;

    public function definition(): array
    {
        $departureDate = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'code' => strtoupper($this->faker->unique()->bothify('TRP-####')),
            'route_id' => TravelRoute::factory(),
            'carrier_id' => null,
            'vehicle_id' => null,
            'departure_date' => $departureDate->format('Y-m-d'),
            'departure_time' => $departureDate->format('H:i:s'),
            'arrival_date' => $departureDate->format('Y-m-d'),
            'arrival_time' => '23:59:00',
            'means_of_transport' => MeansOfTransport::BUS->value,
            'total_seats' => $this->faker->numberBetween(20, 60),
            'status' => TripStatus::DRAFT->value,
            'published_at' => null,
            'created_by_user_id' => null,
        ];
    }
}
