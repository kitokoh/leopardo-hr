<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeOrderDirection;
use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Models\TradeOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeOrder>
 */
class TradeOrderFactory extends Factory
{
    protected $model = TradeOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('ORD-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'direction' => TradeOrderDirection::Purchase->value,
            'trade_deal_id' => null,
            'trade_quote_id' => null,
            'trade_counterparty_id' => null,
            'status' => TradeOrderStatus::Draft->value,
            'currency' => 'XAF',
            'incoterm' => 'FOB',
            'total_minor' => 200_000_000,
            'payment_terms' => null,
            'ordered_at' => null,
            'expected_delivery_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'notes' => null,
            'created_by_employee_id' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => TradeOrderStatus::Confirmed->value,
            'ordered_at' => now(),
        ]);
    }

    /**
     * Commande deja expediee — l'annulation doit alors etre REFUSEE.
     */
    public function shipped(): static
    {
        return $this->state(fn (): array => [
            'status' => TradeOrderStatus::Shipped->value,
            'ordered_at' => now()->subDays(10),
            'shipped_at' => now(),
        ]);
    }
}
