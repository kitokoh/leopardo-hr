<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks remaining leave days per employee per type per year.
 *
 * @property int        $id
 * @property string|int $company_id
 * @property int        $employee_id
 * @property int        $absence_type_id
 * @property int        $year
 * @property float      $balance
 * @property float      $used
 * @property float      $pending
 * @property float      $allocated
 * @property float      $carried_over
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class LeaveBalance extends Model
{
    use BelongsToCompany;

    const CREATED_AT = null;

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
        'balance'    => 'float',
        'used'       => 'float',
        'pending'    => 'float',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForYear(Builder $q, int $year): Builder
    {
        return $q->where('year', $year);
    }
}

