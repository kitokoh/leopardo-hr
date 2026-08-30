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
            'code' => strtoupper($this->faker->unique()->bothify('TAX-##')),
            'label' => 'TVA '.$this->faker->numberBetween(0, 25).'%',
            'rate_bps' => $this->faker->numberBetween(0, 3000),
            'is_default' => false,
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
