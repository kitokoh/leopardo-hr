<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $leave_policy_id
 * @property float $amount
 * @property string $type
 * @property string $description
 * @property Carbon $effective_date
 * @property string|null $created_by
 * @property Carbon|null $created_at
 */
class LeaveAccrual extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'leave_accruals';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_policy_id',
        'amount',
        'type',
        'description',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<LeavePolicy, $this> */
    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
