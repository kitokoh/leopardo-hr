<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de facture de soins — Issue #7791 (BC-31).
 *
 * Prix unitaire FIGÉ au moment de la facturation (jamais recalculé depuis
 * le catalogue) ; `care_act_id` nullable (ligne libre).
 *
 * @property int $id
 * @property string $company_id
 * @property int $invoice_id
 * @property int|null $care_act_id
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

    protected $fillable = [
        'company_id',
        'invoice_id',
        'care_act_id',
        'label',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'care_act_id' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'line_total' => 'decimal:2',
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
