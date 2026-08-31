<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\CarrierType;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCarrier>
 */
class TravelCarrierFactory extends Factory
{
    protected $model = TravelCarrier::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('CAR-###')),
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(CarrierType::cases())->value,
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'logo_asset_id' => null,
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
