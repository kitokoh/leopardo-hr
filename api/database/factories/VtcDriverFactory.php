<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VtcDriver>
 */
class VtcDriverFactory extends Factory
{
    protected $model = VtcDriver::class;

    public function definition(): array
    {
        return [
            // company_id est NOT NULL : hors contexte tenant, le trait
            // BelongsToCompany ne peut pas l'injecter (même leçon #7452).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'user_id' => null,
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'status' => VtcDriverStatus::Offline->value,
            'vehicle_id' => null,
            'current_latitude' => null,
            'current_longitude' => null,
            'location_updated_at' => null,
        ];
    }

    /**
     * Chauffeur en service et localisé (éligible au dispatch VTC-04).
     */
    public function availableAt(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'status' => VtcDriverStatus::Available->value,
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'location_updated_at' => now(),
        ]);
    }
}
