<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks remaining leave days per employee per type per year.
 *
 * @property int   $id
 * @property int   $employee_id
 * @property int   $absence_type_id
 * @property int   $year
 * @property float $allocated
 * @property float $used
 * @property float $balance
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class LeaveBalance extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'employee_id',
        'absence_type_id',
        'year',
        'allocated',
        'used',
        'carried_over',
    ];

    protected $casts = [
        'allocated'    => 'float',
        'used'         => 'float',
        'carried_over' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Core\Auth\Domain\Models\Employee::class);
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }

    public function getBalanceAttribute(): float
    {
        return ($this->allocated + ($this->carried_over ?? 0)) - $this->used;
    }
}
