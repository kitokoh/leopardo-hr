<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelHotel>
 */
class TravelHotelFactory extends Factory
{
    protected $model = TravelHotel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Hotel',
            'city_id' => TravelCity::factory(),
            'classification' => $this->faker->numberBetween(1, 5),
            'address' => $this->faker->optional()->streetAddress(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'description_redacted' => $this->faker->optional()->sentence(),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
