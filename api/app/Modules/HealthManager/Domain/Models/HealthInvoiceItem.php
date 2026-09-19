<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'acte d'une facture de soins — HC-007 (#7791, BC-30).
 *
 * `label` et `unit_price` sont FIGÉS depuis le catalogue AU MOMENT de la
 * facturation (critère HC-007) ; `line_total` = unit_price × quantity,
 * calculé côté serveur. Rien n'est mass-assignable depuis la requête :
 * les lignes sont ENTIÈREMENT construites côté serveur depuis le
 * catalogue (seule la quantité vient du client, validée).
 *
 * @property int $id
 * @property string $company_id
 * @property int $invoice_id
 * @property int $care_act_id
 * @property string $label
 * @property string $unit_price
 * @property int $quantity
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthInvoiceItem extends Model
{
    use BelongsToCompany;

    protected $table = 'health_invoice_items';

    /**
     * Tout est posé CÔTÉ SERVEUR (prix figés du catalogue) ; `company_id`
     * posé par BelongsToCompany (pattern strict #7712).
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<HealthInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(HealthInvoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<HealthCareAct, $this>
     */
    public function careAct(): BelongsTo
    {
        return $this->belongsTo(HealthCareAct::class, 'care_act_id');
    }
}
