<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelRouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRouteStop>
 */
class TravelRouteStopFactory extends Factory
{
    protected $model = TravelRouteStop::class;

    public function definition(): array
    {
        return [
            'route_id' => TravelRoute::factory(),
            'city_id' => TravelCity::factory(),
            'rank' => $this->faker->numberBetween(1, 10),
            'is_stopover' => true,
            'min_duration_min' => $this->faker->optional()->numberBetween(5, 30),
        ];
    }
}
