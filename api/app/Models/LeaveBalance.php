<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $absence_type_id
 * @property float $balance
 * @property float $used
 * @property float $pending
 * @property int $year
 */
class LeaveBalance extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'leave_balances';

    protected $fillable = [
        'company_id',
        'employee_id',
        'absence_type_id',
        'balance',
        'used',
        'pending',
        'year',
    ];

    protected $casts = [
        'balance' => 'float',
        'used' => 'float',
        'pending' => 'float',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<AbsenceType, $this> */
    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    public function scopeForYear(Builder $q, int $year): Builder
    {
        return $q->where('year', $year);
    }
}
