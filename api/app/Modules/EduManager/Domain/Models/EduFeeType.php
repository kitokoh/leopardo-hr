<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Type de frais scolaire d'un établissement — Issue #5832 (EDU-016).
 *
 * Catalogue des frais facturables (scolarité, cantine, transport, …),
 * tenant-scoped (`company_id`), rattaché optionnellement à un campus.
 * `code` unique PAR TENANT ; montant borné (CHECK >= 0) ; devise ISO 4217.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campus_id
 * @property string $code
 * @property string $label
 * @property string $amount
 * @property string $currency
 * @property string $billing_frequency
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduFeeType extends Model
{
    use BelongsToCompany;

    public const FREQUENCY_ONCE = 'once';

    public const FREQUENCY_TERM = 'term';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCIES = [
        self::FREQUENCY_ONCE,
        self::FREQUENCY_TERM,
        self::FREQUENCY_MONTHLY,
    ];

    protected $table = 'edu_fee_types';

    protected $fillable = [
        'company_id',
        'campus_id',
        'code',
        'label',
        'amount',
        'currency',
        'billing_frequency',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'billing_frequency' => 'string',
    ];
}
