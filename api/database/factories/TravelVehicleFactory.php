<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelVehicle>
 */
class TravelVehicleFactory extends Factory
{
    protected $model = TravelVehicle::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('VEH-###')),
            'registration_number' => strtoupper($this->faker->bothify('??-####-??')),
            'seat_capacity' => $this->faker->numberBetween(15, 70),
            'carrier_id' => null,
            'status' => TravelRecordStatus::ACTIVE->value,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
