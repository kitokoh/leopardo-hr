<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelTouristSite>
 */
class TravelTouristSiteFactory extends Factory
{
    protected $model = TravelTouristSite::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->city,
            'description_redacted' => $this->faker->paragraph(2),
            'city_id' => TravelCity::factory(),
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'image_asset_id' => null,
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
