<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTaxRate>
 */
class RestaurantTaxRateFactory extends Factory
{
    protected $model = RestaurantTaxRate::class;

    public function definition(): array
    {
        return [
            // #7452 — company_id est NOT NULL : hors contexte tenant (factory
            // imbriquée 'tax_rate_id' => RestaurantTaxRate::factory()), le trait
            // BelongsToCompany ne peut pas l'injecter (cf. RestaurantBranchFactory).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('TAX-##')),
            'label' => 'TVA '.$this->faker->numberBetween(0, 25).'%',
            'rate_bps' => $this->faker->numberBetween(0, 3000),
            'is_default' => false,
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
