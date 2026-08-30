<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCurrencyRate>
 */
class TravelCurrencyRateFactory extends Factory
{
    protected $model = TravelCurrencyRate::class;

    public function definition(): array
    {
        return [
            'from_currency' => 'EUR',
            'to_currency' => 'XOF',
            'rate_minor' => 65595700, // 1 EUR ≈ 655,957 XOF × 10000
            'valid_from' => $this->faker->date(),
            'valid_to' => null,
        ];
    }
}
