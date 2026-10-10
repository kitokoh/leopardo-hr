<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Vtc\Domain\Enums\VtcVehicleCategory;
use App\Modules\Vtc\Domain\Enums\VtcVehicleStatus;
use App\Modules\Vtc\Domain\Models\VtcVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VtcVehicle>
 */
class VtcVehicleFactory extends Factory
{
    protected $model = VtcVehicle::class;

    public function definition(): array
    {
        return [
            // company_id est NOT NULL : hors contexte tenant, le trait
            // BelongsToCompany ne peut pas l'injecter (même leçon #7452).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'plate' => strtoupper($this->faker->unique()->bothify('??-###-??')),
            'brand' => $this->faker->randomElement(['Toyota', 'Hyundai', 'Suzuki', 'Kia']),
            'model' => $this->faker->randomElement(['Corolla', 'Accent', 'Swift', 'Rio']),
            'color' => $this->faker->safeColorName(),
            'seats' => 4,
            'category' => VtcVehicleCategory::Berline->value,
            'status' => VtcVehicleStatus::Active->value,
        ];
    }
}
