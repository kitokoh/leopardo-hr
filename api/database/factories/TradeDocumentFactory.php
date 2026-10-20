<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeDocumentStatus;
use App\Modules\Trade\Domain\Enums\TradeDocumentType;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeDocument>
 */
class TradeDocumentFactory extends Factory
{
    protected $model = TradeDocument::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_deal_id' => TradeDeal::factory(),
            'trade_order_id' => null,
            'trade_quote_id' => null,
            'trade_rfq_id' => null,
            'type' => TradeDocumentType::ProformaInvoice->value,
            'title' => 'Facture pro forma',
            // Disque prive : le fichier n'est jamais expose en URL publique.
            'disk' => 'private',
            'path' => 'trade/documents/'.$this->faker->uuid().'.pdf',
            'original_name' => 'proforma.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => TradeDocumentStatus::Draft->value,
            'uploaded_by_employee_id' => null,
            'shared_at' => null,
        ];
    }
}
