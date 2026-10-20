<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeCustomsCaseStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeCustomsCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cas d'AIDE A LA PREPARATION DOUANIERE — BC-36 TRADE, TRADE-007/#8482.
 *
 * ⚠️ CONFORMITE LEGALE : Leopardo facilite l'ESTIMATION, la PREPARATION
 * DOCUMENTAIRE et la MISE EN RELATION. Il ne realise ni ne pretend realiser
 * une declaration officielle : aucun champ ni libelle de ce modele ne doit
 * laisser croire a une decision douaniere ou a une garantie de conformite.
 *
 * `duty_rate_percent` est nullable et sa provenance (`duty_source`) est
 * tracee : sans fournisseur de tarifs configure, le taux reste nul et le cas
 * est signale comme non estime — jamais comble par une valeur plausible.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $trade_deal_id
 * @property int|null $trade_order_id
 * @property string $product_description
 * @property string|null $hs_code
 * @property string $origin_country
 * @property string $destination_country
 * @property TradeCustomsCaseStatus $status
 * @property list<string>|null $required_documents
 * @property list<string>|null $missing_info
 * @property string|null $duty_rate_percent
 * @property string|null $duty_source
 * @property int|null $duty_amount_minor
 * @property Carbon|null $disclaimer_acknowledged_at
 */
final class TradeCustomsCase extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeCustomsCaseFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_customs_cases';

    protected $fillable = [
        'trade_deal_id',
        'trade_order_id',
        'product_description',
        'hs_code',
        'origin_country',
        'destination_country',
        'status',
        'required_documents',
        'missing_info',
        'duty_rate_percent',
        'duty_source',
        'duty_amount_minor',
        'duty_currency',
        'taxes',
        'disclaimer_acknowledged_at',
        'notes',
        'created_by_employee_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_deal_id' => 'integer',
            'trade_order_id' => 'integer',
            'status' => TradeCustomsCaseStatus::class,
            'required_documents' => 'array',
            'missing_info' => 'array',
            'duty_amount_minor' => 'integer',
            'taxes' => 'array',
            'disclaimer_acknowledged_at' => 'datetime',
            'created_by_employee_id' => 'integer',
        ];
    }

    /**
     * L'utilisateur a-t-il pris connaissance du fait que ceci n'est pas une
     * decision douaniere officielle ? Requis avant tout partage ou export.
     */
    public function isDisclaimerAcknowledged(): bool
    {
        return $this->disclaimer_acknowledged_at !== null;
    }
}
