<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    use BelongsToCompany;

    protected $table = 'leave_policies';

    protected $fillable = [
        'company_id',
        'absence_type_id',
        'name',
        'accrual_type',
        'accrual_amount',
        'max_balance',
        'carry_forward',
        'carry_forward_max',
        'carry_forward_expiry_days',
        'requires_approval',
        'approval_levels',
        'min_notice_days',
        'max_consecutive_days',
        'applicable_roles',
        'active',
    ];

    protected $casts = [
        'accrual_amount' => 'float',
        'max_balance' => 'float',
        'carry_forward' => 'boolean',
        'carry_forward_max' => 'float',
        'requires_approval' => 'boolean',
        'applicable_roles' => 'array',
        'active' => 'boolean',
    ];

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class, 'leave_policy_id');
    }
}
