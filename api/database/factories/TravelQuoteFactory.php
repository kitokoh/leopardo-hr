<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelQuote>
 */
class TravelQuoteFactory extends Factory
{
    protected $model = TravelQuote::class;

    public function definition(): array
    {
        return [
            'reference' => 'QT-'.strtoupper($this->faker->bothify('??????????')),
            'trip_id' => TravelTrip::factory(),
            'status' => 'draft',
            'customer_contact_id' => null,
            'passenger_count' => TravelQuote::MIN_GROUP_SIZE,
            'total_amount_minor' => 75000,
            'currency' => 'XOF',
            'expires_at' => null,
            'booking_id' => null,
            'idempotency_key' => $this->faker->unique()->uuid(),
            'created_by_user_id' => null,
        ];
    }
}
