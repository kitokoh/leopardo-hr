<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicleImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelRentalVehicleImage>
 */
class TravelRentalVehicleImageFactory extends Factory
{
    protected $model = TravelRentalVehicleImage::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => TravelRentalVehicle::factory(),
            'asset_id' => $this->faker->numberBetween(1, 100000),
            'position' => $this->faker->numberBetween(0, 5),
        ];
    }
}
