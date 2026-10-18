<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeCustomsCaseStatus;
use App\Modules\Trade\Domain\Models\TradeCustomsCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeCustomsCase>
 */
class TradeCustomsCaseFactory extends Factory
{
    protected $model = TradeCustomsCase::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_deal_id' => null,
            'trade_order_id' => null,
            'product_description' => 'Cafe vert non torrefie',
            // Code HS absent par defaut : jamais devine par le systeme.
            'hs_code' => null,
            'origin_country' => 'VN',
            'destination_country' => 'CM',
            'status' => TradeCustomsCaseStatus::Incomplete->value,
            'required_documents' => null,
            'missing_info' => null,
            // Aucun taux devine : null tant qu'une source n'est pas disponible.
            'duty_rate_percent' => null,
            'duty_source' => null,
            'duty_amount_minor' => null,
            'duty_currency' => null,
            'taxes' => null,
            'disclaimer_acknowledged_at' => null,
            'notes' => null,
            'created_by_employee_id' => null,
        ];
    }
}
