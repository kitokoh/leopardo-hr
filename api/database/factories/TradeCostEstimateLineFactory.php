<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeCostLineType;
use App\Modules\Trade\Domain\Enums\TradeCostSource;
use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Domain\Models\TradeCostEstimateLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeCostEstimateLine>
 */
class TradeCostEstimateLineFactory extends Factory
{
    protected $model = TradeCostEstimateLine::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_cost_estimate_id' => TradeCostEstimate::factory(),
            'type' => TradeCostLineType::InternationalFreight->value,
            'label' => null,
            'amount_minor' => 4_500_000,
            'currency' => 'XAF',
            'converted_amount_minor' => 4_500_000,
            'source' => TradeCostSource::UserInput->value,
            'note' => null,
            'position' => 0,
        ];
    }

    /**
     * Poste non chiffre : exclu du total, jamais comble par une valeur inventee.
     */
    public function unavailable(): static
    {
        return $this->state(fn (): array => [
            'amount_minor' => 0,
            'converted_amount_minor' => null,
            'source' => TradeCostSource::Unavailable->value,
            'note' => 'Tarif non communique.',
        ]);
    }
}
