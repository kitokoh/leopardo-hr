<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VtcRide>
 */
class VtcRideFactory extends Factory
{
    protected $model = VtcRide::class;

    public function definition(): array
    {
        return [
            // company_id est NOT NULL : hors contexte tenant, le trait
            // BelongsToCompany ne peut pas l'injecter (même leçon #7452).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'reference' => sprintf('VTC-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'passenger_user_id' => null,
            'passenger_name' => $this->faker->name(),
            'passenger_phone' => $this->faker->phoneNumber(),
            // Douala (centre) par défaut — coordonnées WGS 84 valides.
            'pickup_latitude' => 4.0511,
            'pickup_longitude' => 9.7679,
            'pickup_address' => null,
            'dropoff_latitude' => 4.0611,
            'dropoff_longitude' => 9.7779,
            'dropoff_address' => null,
            'status' => VtcRideStatus::Requested->value,
            'fare_profile_id' => null,
            'estimated_distance_m' => null,
            'estimated_duration_s' => null,
            'estimated_price_minor' => null,
            'final_price_minor' => null,
            'currency' => 'XAF',
            'driver_id' => null,
            'requested_at' => now(),
            'idempotency_key' => null,
            'metadata' => null,
        ];
    }
}
