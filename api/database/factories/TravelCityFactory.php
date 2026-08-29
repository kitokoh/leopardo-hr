<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCity>
 */
class TravelCityFactory extends Factory
{
    protected $model = TravelCity::class;

    public function definition(): array
    {
        return [
            'country_iso2' => 'CM',
            'name' => $this->faker->city(),
            'region' => $this->faker->optional()->word(),
            'latitude' => $this->faker->optional()->latitude(),
            'longitude' => $this->faker->optional()->longitude(),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
