<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Document comptable (facture, proforma, devis, avoir, irsaliye, reçu) —
 * COMPTABILITE_CONCEPTION.md §4 (table unique, type discriminé).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $type
 * @property string $number
 * @property string $status
 * @property int|null $contact_id
 * @property string|null $project_ref
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $delivery_date
 * @property string|null $currency
 * @property float|null $exchange_rate
 * @property float $subtotal_ht
 * @property float $tax_amount
 * @property float $total_ttc
 * @property float|null $tva_rate
 * @property string|null $notes
 * @property string|null $footer_mentions
 * @property string|null $pdf_path
 * @property Carbon|null $sent_at
 * @property float $paid_amount
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountingContact|null $contact
 * @property-read Collection<int, AccountingDocumentLine> $lines
 * @property-read Collection<int, AccountingPayment> $payments
 *
 * @mixin Builder<static>
 */
class AccountingDocument extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_documents';

    protected $fillable = [
        'company_id',
        'type',
        'number',
        'status',
        'contact_id',
        'project_ref',
        'issue_date',
        'due_date',
        'delivery_date',
        'currency',
        'exchange_rate',
        'subtotal_ht',
        'tax_amount',
        'total_ttc',
        'tva_rate',
        'notes',
        'footer_mentions',
        'pdf_path',
        'sent_at',
        'paid_amount',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'delivery_date' => 'date',
        'sent_at' => 'datetime',
        'exchange_rate' => 'float',
        'subtotal_ht' => 'float',
        'tax_amount' => 'float',
        'total_ttc' => 'float',
        'tva_rate' => 'float',
        'paid_amount' => 'float',
        'metadata' => 'encrypted:array',
    ];

    /** @return BelongsTo<AccountingContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(AccountingContact::class, 'contact_id');
    }

    /** @return HasMany<AccountingDocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class, 'document_id')->orderBy('sort_order');
    }

    /** @return HasMany<AccountingPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AccountingPayment::class, 'document_id');
    }
}
