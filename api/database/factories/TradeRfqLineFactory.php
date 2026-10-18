<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Models\TradeRfqLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeRfqLine>
 */
class TradeRfqLineFactory extends Factory
{
    protected $model = TradeRfqLine::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_rfq_id' => TradeRfq::factory(),
            // Saisie LIBRE : le produit catalogue est optionnel par conception.
            'trade_product_id' => null,
            'description' => $this->faker->words(4, true),
            'specs' => null,
            'quantity' => '1000.000',
            'unit' => 'kg',
            'target_unit_price_minor' => 200_000,
            'position' => 0,
        ];
    }
}
