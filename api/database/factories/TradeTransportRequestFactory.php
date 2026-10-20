<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeTransportMode;
use App\Modules\Trade\Domain\Enums\TradeTransportRequestStatus;
use App\Modules\Trade\Domain\Models\TradeTransportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeTransportRequest>
 */
class TradeTransportRequestFactory extends Factory
{
    protected $model = TradeTransportRequest::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'reference' => sprintf('TTR-%s-%06d', now()->format('Y'), $this->faker->unique()->numberBetween(1, 999_999)),
            'trade_deal_id' => null,
            'trade_order_id' => null,
            'origin_country' => 'VN',
            'origin_city' => 'Ho Chi Minh',
            'origin_port' => 'Cat Lai',
            'destination_country' => 'CM',
            'destination_city' => 'Douala',
            'destination_port' => 'Douala',
            'mode' => TradeTransportMode::Sea->value,
            'cargo_description' => 'Cafe vert en sacs de 60 kg',
            'weight_kg' => '20000.000',
            'volume_m3' => '28.000',
            'containers' => ['1x20DV'],
            'incoterm' => 'FOB',
            'pickup_date' => now()->addDays(10)->toDateString(),
            'status' => TradeTransportRequestStatus::Draft->value,
            'created_by_employee_id' => null,
            'sent_at' => null,
            'closed_at' => null,
            'selected_quote_id' => null,
        ];
    }
}
