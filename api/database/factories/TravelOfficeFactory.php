<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelOffice>
 */
class TravelOfficeFactory extends Factory
{
    protected $model = TravelOffice::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Bureau',
            'city_id' => TravelCity::factory(),
            'address' => $this->faker->optional()->streetAddress(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
