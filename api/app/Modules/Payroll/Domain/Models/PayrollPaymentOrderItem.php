<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'un {@see PayrollPaymentOrder} : net à payer d'un employé + IBAN.
 *
 * @property int $id
 * @property int $payment_order_id
 * @property int $employee_id
 * @property float $net_amount
 * @property string|null $iban
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PayrollPaymentOrderItem extends Model
{
    protected $fillable = [
        'payment_order_id', 'employee_id', 'net_amount', 'iban',
    ];

    protected $casts = [
        'net_amount' => 'float',
    ];

    /** @return BelongsTo<PayrollPaymentOrder, $this> */
    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PayrollPaymentOrder::class);
    }
}
