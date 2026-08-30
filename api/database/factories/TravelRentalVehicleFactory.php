<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRentalVehicle>
 */
class TravelRentalVehicleFactory extends Factory
{
    protected $model = TravelRentalVehicle::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('RENT-###')),
            'title' => $this->faker->words(3, true),
            'city_id' => TravelCity::factory(),
            'price_per_day_minor' => $this->faker->numberBetween(10000, 100000),
            'currency' => 'XAF',
            'available_from' => null,
            'available_until' => null,
            'owner_carrier_id' => null,
            'status' => TravelRecordStatus::ACTIVE->value,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
