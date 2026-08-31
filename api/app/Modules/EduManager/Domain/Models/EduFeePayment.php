<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Encaissement sur une charge de frais scolaire — Issue #5832 (EDU-016).
 *
 * Idempotent par `external_id` (unique PAR TENANT) ; méthode bornée
 * (cash|transfer|card|mobile_money|other) ; montant strictement positif.
 * Le contrôle de non-surdébit (total des paiements ≤ montant de la charge)
 * est porté par EduFeeService (EDU_FEE_OVERPAYMENT) et par la transition de
 * statut de la charge.
 *
 * @property int $id
 * @property string $company_id
 * @property int $fee_charge_id
 * @property string $amount
 * @property string $currency
 * @property string $method
 * @property string|null $reference
 * @property string|null $external_id
 * @property Carbon $paid_at
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduFeePayment extends Model
{
    use BelongsToCompany;

    public const METHOD_CASH = 'cash';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_CARD = 'card';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_TRANSFER,
        self::METHOD_CARD,
        self::METHOD_MOBILE_MONEY,
        self::METHOD_OTHER,
    ];

    protected $table = 'edu_fee_payments';

    protected $fillable = [
        'company_id',
        'fee_charge_id',
        'amount',
        'currency',
        'method',
        'reference',
        'external_id',
        'paid_at',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<EduFeeCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(EduFeeCharge::class, 'fee_charge_id');
    }
}
