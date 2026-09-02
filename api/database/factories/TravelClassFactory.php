<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelClass>
 */
class TravelClassFactory extends Factory
{
    protected $model = TravelClass::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('CLS-###')),
            'label' => $this->faker->randomElement(['Économique', 'Confort', 'Business']),
            'color' => $this->faker->safeHexColor(),
            'priority' => $this->faker->numberBetween(0, 10),
            'status' => TravelRecordStatus::ACTIVE->value,
        ];
    }
}
