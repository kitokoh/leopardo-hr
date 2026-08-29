<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTripPrice>
 */
class TravelTripPriceFactory extends Factory
{
    protected $model = TravelTripPrice::class;

    public function definition(): array
    {
        return [
            'trip_id' => TravelTrip::factory(),
            'class_id' => TravelClass::factory(),
            'adult_price_minor' => $this->faker->numberBetween(50000, 500000),
            'child_price_minor' => $this->faker->optional()->numberBetween(25000, 250000),
            'currency' => 'XAF',
        ];
    }
}
