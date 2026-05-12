<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $absence_type_id
 * @property string $name
 * @property string $accrual_type
 * @property float $accrual_amount
 * @property float $max_balance
 * @property bool $carry_forward
 * @property float $carry_forward_max
 * @property int $carry_forward_expiry_days
 * @property bool $requires_approval
 * @property int $approval_levels
 * @property int $min_notice_days
 * @property int $max_consecutive_days
 * @property array<mixed> $applicable_roles
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return BelongsTo<AbsenceType, $this> */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    /** @return HasMany<LeaveAccrual, $this> */
    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class, 'leave_policy_id');
    }
}
