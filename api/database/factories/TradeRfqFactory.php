<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Modules\Trade\Domain\Models\TradeRfq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeRfq>
 */
class TradeRfqFactory extends Factory
{
    protected $model = TradeRfq::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('RFQ-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'trade_deal_id' => null,
            'title' => 'Recherche '.$this->faker->sentence(3),
            'status' => TradeRfqStatus::Draft->value,
            'destination_country' => 'CM',
            'destination_port' => 'Douala',
            'incoterm' => 'FOB',
            'budget_minor' => 5_000_000,
            'budget_currency' => 'XAF',
            'deadline_at' => now()->addDays(15),
            'notes' => null,
            'created_by_employee_id' => null,
            'published_at' => null,
            'closed_at' => null,
            'awarded_at' => null,
            'awarded_quote_id' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => TradeRfqStatus::Open->value,
            'published_at' => now(),
        ]);
    }
}
