<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelSalesSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelSalesSettlement>
 */
class TravelSalesSettlementFactory extends Factory
{
    protected $model = TravelSalesSettlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'currency' => 'XAF',
            'confirmed_payments_count' => 0,
            'confirmed_amount_minor' => 0,
            'refunded_count' => 0,
            'refunded_amount_minor' => 0,
            'net_amount_minor' => 0,
            'status' => TravelSalesSettlement::STATUS_SETTLED,
            'settled_at' => now(),
        ];
    }
}
