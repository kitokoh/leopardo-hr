<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelStation>
 */
class TravelStationFactory extends Factory
{
    protected $model = TravelStation::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('GAR-###')),
            'name' => $this->faker->company().' Gare',
            'city_id' => TravelCity::factory(),
            'address' => $this->faker->optional()->streetAddress(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'timezone' => 'Africa/Douala',
            'is_terminal' => $this->faker->boolean(30),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
