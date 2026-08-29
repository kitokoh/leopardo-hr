<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCountry>
 */
class TravelCountryFactory extends Factory
{
    protected $model = TravelCountry::class;

    public function definition(): array
    {
        $iso2 = $this->faker->unique()->countryCode();

        return [
            'iso2' => $iso2,
            'iso3' => $this->faker->lexify('???'),
            'name' => $this->faker->country(),
            'phone_code' => $this->faker->numberBetween(1, 999),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
