<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'un document comptable — COMPTABILITE_CONCEPTION.md §4.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $document_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property float $discount
 * @property int|null $tax_rate_id
 * @property float|null $tax_rate
 * @property float $tax_amount
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountingDocument $document
 *
 * @mixin Builder<static>
 */
class AccountingDocumentLine extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_document_lines';

    protected $fillable = [
        'company_id',
        'document_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax_rate_id',
        'tax_rate',
        'tax_amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'sort_order' => 'integer',
    ];

    /**
     * Montant HT de la ligne, remise déduite (issue #9376).
     *
     * `discount` est un montant absolu, pas un pourcentage — c'est la
     * convention déjà appliquée par `DocumentWorkflowService::createDraft()`
     * (`quantity * unit_price - discount`).
     */
    public function lineSubtotal(): float
    {
        $gross = (float) $this->quantity * (float) $this->unit_price;

        return round(max(0.0, $gross - (float) ($this->discount ?? 0)), 2);
    }

    /**
     * Montant de taxe de la ligne. Le taux dénormalisé prime sur le référentiel :
     * une facture passée garde le taux appliqué au moment de son émission.
     */
    public function computedTaxAmount(): float
    {
        $rate = $this->tax_rate;

        if ($rate === null) {
            return round((float) ($this->tax_amount ?? 0), 2);
        }

        return round($this->lineSubtotal() * (float) $rate / 100, 2);
    }

    /** @return BelongsTo<AccountingDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }
}
