<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paiement / encaissement rattaché à un document comptable —
 * COMPTABILITE_CONCEPTION.md §4.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $document_id
 * @property float $amount
 * @property string $method
 * @property string|null $reference
 * @property string|null $gateway_payment_id
 * @property Carbon|null $received_at
 * @property Carbon|null $reconciled_at
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountingDocument $document
 *
 * @mixin Builder<static>
 */
class AccountingPayment extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_payments';

    protected $fillable = [
        'company_id',
        'document_id',
        'amount',
        'method',
        'reference',
        'gateway_payment_id',
        'received_at',
        'reconciled_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'received_at' => 'date',
        'reconciled_at' => 'date',
        // Référence bancaire (n° chèque/RIB) — donnée sensible chiffrée au repos.
        'reference' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];

    /** @return BelongsTo<AccountingDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }
}
