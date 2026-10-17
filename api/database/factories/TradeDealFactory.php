<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeDealDirection;
use App\Modules\Trade\Domain\Enums\TradeDealStatus;
use App\Modules\Trade\Domain\Models\TradeDeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeDeal>
 */
class TradeDealFactory extends Factory
{
    protected $model = TradeDeal::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('TRD-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'direction' => TradeDealDirection::Import->value,
            'trade_counterparty_id' => null,
            'title' => 'Dossier '.implode(' ', $this->faker->words(3)),
            'status' => TradeDealStatus::Open->value,
            'currency' => 'XAF',
            'opened_by_employee_id' => null,
            'metadata' => null,
            'closed_at' => null,
        ];
    }
}
