<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
