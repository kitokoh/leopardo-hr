<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeQuoteDirection;
use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Models\TradeQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeQuote>
 */
class TradeQuoteFactory extends Factory
{
    protected $model = TradeQuote::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('QTE-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'version' => 1,
            'supersedes_id' => null,
            'direction' => TradeQuoteDirection::Outgoing->value,
            'trade_rfq_id' => null,
            'trade_rfq_invitation_id' => null,
            'trade_deal_id' => null,
            'trade_counterparty_id' => null,
            'status' => TradeQuoteStatus::Draft->value,
            'currency' => 'XAF',
            'incoterm' => 'FOB',
            'validity_until' => now()->addDays(30)->toDateString(),
            'lead_time_days' => 30,
            'payment_terms' => '30% acompte, solde a vue',
            'subtotal_minor' => 0,
            'total_minor' => 0,
            'notes' => null,
            'created_by_employee_id' => null,
            'submitted_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => TradeQuoteStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => TradeQuoteStatus::Accepted->value,
            'submitted_at' => now()->subDay(),
            'accepted_at' => now(),
        ]);
    }
}
