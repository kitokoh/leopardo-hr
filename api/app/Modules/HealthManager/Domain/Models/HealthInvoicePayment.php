<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paiement d'une facture de soins — HC-007 (#7791, BC-30).
 *
 * Montant, mode (cash|card|transfer|cheque|insurance), date, référence.
 * Enregistré SOUS TRANSACTION avec verrou sur la facture : le sur-paiement
 * est refusé (422 HEALTH_INVOICE_OVERPAYMENT) et le statut de la facture
 * (partially_paid | paid) est dérivé du solde EXACT. Un paiement ne se
 * modifie ni ne se supprime via l'API (piste d'audit comptable).
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

    public const METHOD_CHEQUE = 'cheque';

    public const METHOD_INSURANCE = 'insurance';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_TRANSFER,
        self::METHOD_CHEQUE,
        self::METHOD_INSURANCE,
    ];

    protected $table = 'health_invoice_payments';

    /**
     * `invoice_id` posé côté serveur (facture verrouillée du tenant) ;
     * `company_id` posé par BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'amount',
        'method',
        'paid_at',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
