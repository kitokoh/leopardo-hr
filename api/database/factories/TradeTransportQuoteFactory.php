<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeTransportQuoteStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeTransportQuote;
use App\Modules\Trade\Domain\Models\TradeTransportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeTransportQuote>
 */
class TradeTransportQuoteFactory extends Factory
{
    protected $model = TradeTransportQuote::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_transport_request_id' => TradeTransportRequest::factory(),
            'trade_counterparty_id' => TradeCounterparty::factory(),
            'price_minor' => 4_500_000,
            'currency' => 'XAF',
            // Perimetre de cout EXPLICITE — condition de la comparabilite.
            'included_fees' => ['fret maritime', 'THC origine'],
            'excluded_fees' => ['droits de douane', 'transport interieur destination'],
            'transit_time_days' => 28,
            'validity_until' => now()->addDays(14)->toDateString(),
            'status' => TradeTransportQuoteStatus::Received->value,
            'notes' => null,
            'received_at' => now(),
            'decided_at' => null,
        ];
    }
}
