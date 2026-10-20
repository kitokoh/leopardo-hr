<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeMessageDirection;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeMessage>
 */
class TradeMessageFactory extends Factory
{
    protected $model = TradeMessage::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_deal_id' => TradeDeal::factory(),
            'direction' => TradeMessageDirection::Internal->value,
            'body' => $this->faker->paragraph(),
            'attachments' => null,
            'employee_id' => null,
            // Aucun envoi n'a eu lieu : `sent_at` reste null.
            'sent_via' => null,
            'sent_at' => null,
        ];
    }
}
