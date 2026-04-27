<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'delta'        => 'float',
        'balance_after' => 'float',
        'created_at'   => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
