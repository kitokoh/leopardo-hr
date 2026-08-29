<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelHotel;
use App\Modules\TravelAgency\Domain\Models\TravelHotelRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelHotelRoom>
 */
class TravelHotelRoomFactory extends Factory
{
    protected $model = TravelHotelRoom::class;

    public function definition(): array
    {
        return [
            'hotel_id' => TravelHotel::factory(),
            'type_code' => $this->faker->randomElement(['single', 'double', 'suite']),
            'room_number' => (string) $this->faker->unique()->numberBetween(100, 999),
            'capacity' => $this->faker->numberBetween(1, 4),
            'price_per_night_minor' => $this->faker->numberBetween(10000, 100000),
            'currency' => 'XAF',
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
