<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int|null $payroll_run_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string $status
 * @property float $total_amount
 * @property string $currency
 * @property int $items_count
 * @property int|null $created_by
 * @property int|null $marked_paid_by
 * @property Carbon|null $marked_paid_at
 * @property Carbon|null $confirmed_at
 * @property array<string, mixed>|null $metadata
 * @property-read PayrollRun|null $payrollRun
 */
class PaymentBatch extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIALLY_CONFIRMED = 'partially_confirmed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'period_start',
        'period_end',
        'status',
        'total_amount',
        'currency',
        'items_count',
        'created_by',
        'marked_paid_by',
        'marked_paid_at',
        'confirmed_at',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'float',
        'items_count' => 'integer',
        'marked_paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return HasMany<PaymentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'payment_batch_id');
    }
}
