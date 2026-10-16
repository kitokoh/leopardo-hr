<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeShipmentStatus;
use App\Modules\Trade\Domain\Enums\TradeTransportMode;
use App\Modules\Trade\Domain\Models\TradeOrder;
use App\Modules\Trade\Domain\Models\TradeShipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeShipment>
 */
class TradeShipmentFactory extends Factory
{
    protected $model = TradeShipment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_order_id' => TradeOrder::factory(),
            'mode' => TradeTransportMode::Sea->value,
            'carrier_name' => $this->faker->company(),
            // Reference de suivi SAISIE : aucun tracking externe n'est invente.
            'tracking_reference' => strtoupper($this->faker->bothify('??########')),
            'status' => TradeShipmentStatus::Pending->value,
            'origin' => 'Douala',
            'destination' => 'Marseille',
            'eta' => now()->addDays(25),
            'shipped_at' => null,
            'arrived_at' => null,
            'delivered_at' => null,
            'notes' => null,
        ];
    }
}
