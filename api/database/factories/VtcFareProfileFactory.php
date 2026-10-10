<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Vtc\Domain\Models\VtcFareProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VtcFareProfile>
 */
class VtcFareProfileFactory extends Factory
{
    protected $model = VtcFareProfile::class;

    public function definition(): array
    {
        return [
            // company_id est NOT NULL : hors contexte tenant, le trait
            // BelongsToCompany ne peut pas l'injecter (même leçon #7452).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'name' => 'Standard',
            'currency' => 'XAF',
            'base_minor' => 500_00,
            'per_km_minor' => 250_00,
            'per_minute_minor' => 50_00,
            'minimum_minor' => 1_000_00,
            'is_default' => false,
        ];
    }
}
