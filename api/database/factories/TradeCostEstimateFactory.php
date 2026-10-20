<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeCostEstimate>
 */
class TradeCostEstimateFactory extends Factory
{
    protected $model = TradeCostEstimate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('LCE-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'trade_deal_id' => null,
            'trade_quote_id' => null,
            'trade_order_id' => null,
            'origin_country' => 'VN',
            'destination_country' => 'CM',
            'incoterm' => 'FOB',
            'goods_value_minor' => 200_000_000,
            'goods_currency' => 'XAF',
            'display_currency' => 'XAF',
            // Aucun taux devine : null tant qu'il n'est pas fourni ou recupere.
            'fx_rate' => null,
            'fx_rate_date' => null,
            'fx_source' => null,
            'total_minor' => 0,
            'status' => 'draft',
            'notes' => null,
            'created_by_employee_id' => null,
            'finalized_at' => null,
        ];
    }
}
