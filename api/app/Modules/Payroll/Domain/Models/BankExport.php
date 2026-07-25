<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $payroll_run_id
 * @property string|null $company_id
 * @property string|null $format
 * @property string|null $file_path
 * @property string|null $error_message
 * @property float $total_amount
 * @property int $transfer_count
 * @property string $status
 * @property Carbon|null $generated_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class BankExport extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SENT = 'sent';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'payroll_run_id', 'company_id', 'format', 'file_path', 'error_message',
        'total_amount', 'transfer_count', 'status',
        'generated_at', 'sent_at',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'transfer_count' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<PayrollRun, $this> */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
