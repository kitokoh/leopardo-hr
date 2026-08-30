<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelRoundTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRoundTrip>
 */
class TravelRoundTripFactory extends Factory
{
    protected $model = TravelRoundTrip::class;

    public function definition(): array
    {
        return [
            'reference' => 'RT-'.strtoupper($this->faker->bothify('??????????')),
            'booking_outbound_id' => TravelBooking::factory(),
            'booking_return_id' => TravelBooking::factory(),
            'idempotency_key' => $this->faker->unique()->uuid(),
            'created_by_user_id' => null,
        ];
    }
}
