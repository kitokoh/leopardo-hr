<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ordre de virement préparé depuis le net par employé d'un
 * {@see PayrollRun} validé, puis exécuté par le comptable (issue #5239 —
 * flux Paie → Comptabilité, Phase C).
 *
 * Cycle de vie : `prepared` → `executed` → `reconciled`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $payroll_run_id
 * @property string $status
 * @property string $format
 * @property string|null $file_path
 * @property float $total_amount
 * @property int $transfer_count
 * @property string|null $bank_reference
 * @property int|null $executed_by
 * @property Carbon|null $executed_at
 * @property Carbon|null $reconciled_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PayrollPaymentOrder extends Model
{
    use BelongsToCompany;

    public const STATUS_PREPARED = 'prepared';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'company_id', 'payroll_run_id', 'status', 'format', 'file_path',
        'total_amount', 'transfer_count', 'bank_reference', 'executed_by',
        'executed_at', 'reconciled_at', 'created_by',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'transfer_count' => 'integer',
        'executed_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /** @return HasMany<PayrollPaymentOrderItem, $this> */
    public function items(): HasMany
    {
        // La migration crée `payment_order_id` (pas la convention
        // `payroll_payment_order_id`) — FK explicite pour éviter SQLSTATE 42703.
        return $this->hasMany(PayrollPaymentOrderItem::class, 'payment_order_id');
    }
}
