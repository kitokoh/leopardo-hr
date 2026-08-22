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
 * @property string|null $tax_id
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
        'tax_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<AccountingDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }
}
