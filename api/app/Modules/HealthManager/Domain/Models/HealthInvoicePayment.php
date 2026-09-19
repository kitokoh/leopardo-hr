<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Encaissement d'une facture de soins — Issue #7791 (BC-30).
 *
 * Invariants (spec §4, service) : cumul ≤ total (sur-paiement 422) ;
 * cumul = total → facture `paid`, sinon `partially_paid`. Méthode bornée
 * (CHECK en base).
 *
 * @property int $id
 * @property string $company_id
 * @property int $invoice_id
 * @property string $amount
 * @property string $method
 * @property Carbon $paid_at
 * @property string|null $reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthInvoicePayment extends Model
{
    use BelongsToCompany;

    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_MOBILE = 'mobile';

    public const METHOD_INSURANCE = 'insurance';

    public const METHOD_OTHER = 'other';

    /** @var list<string> */
    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_TRANSFER,
        self::METHOD_MOBILE,
        self::METHOD_INSURANCE,
        self::METHOD_OTHER,
    ];

    protected $table = 'health_invoice_payments';

    protected $fillable = [
        'company_id',
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'reference',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'amount' => 'decimal:2',
        'method' => 'string',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<HealthInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(HealthInvoice::class, 'invoice_id');
    }
}
