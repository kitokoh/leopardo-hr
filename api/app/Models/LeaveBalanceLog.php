<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property float $delta
 * @property string|null $reason
 * @property int|null $reference_id
 * @property float $balance_after
 * @property Carbon|null $created_at
 */
class LeaveBalanceLog extends Model
{
    use BelongsToCompany;

    protected $table = 'leave_balance_logs';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'employee_id',
        'delta',
        'reason',
        'reference_id',
        'balance_after',
    ];

    protected $casts = [
        'delta' => 'float',
        'balance_after' => 'float',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
