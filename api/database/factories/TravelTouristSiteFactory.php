<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTouristSite>
 */
class TravelTouristSiteFactory extends Factory
{
    protected $model = TravelTouristSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description_redacted' => $this->faker->paragraph(),
            'city_id' => 1,
            'latitude' => null,
            'longitude' => null,
            'images' => [],
            'status' => 'active',
        ];
    }
}
