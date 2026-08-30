<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRoute>
 */
class TravelRouteFactory extends Factory
{
    protected $model = TravelRoute::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('RTE-###')),
            'origin_city_id' => TravelCity::factory(),
            'destination_city_id' => TravelCity::factory(),
            'distance_km' => $this->faker->numberBetween(20, 900),
            'duration_min' => $this->faker->numberBetween(30, 720),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
