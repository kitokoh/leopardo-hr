<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Models\TradeQuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeQuoteLine>
 */
class TradeQuoteLineFactory extends Factory
{
    protected $model = TradeQuoteLine::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_quote_id' => TradeQuote::factory(),
            'trade_product_id' => null,
            'description' => $this->faker->words(4, true),
            'quantity' => '1000.000',
            'unit' => 'kg',
            'unit_price_minor' => 200_000,
            'moq_quantity' => '1000.000',
            'lead_time_days' => 30,
            // Le total de ligne est calcule cote serveur ; cette valeur n'est
            // qu'un defaut de fixture (les actions la recalculent toujours).
            'line_total_minor' => 200_000_000,
            'position' => 0,
        ];
    }
}
