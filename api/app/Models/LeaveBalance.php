<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class, 'absence_type_id');
    }

    public function scopeForYear(Builder $q, int $year): Builder
    {
        return $q->where('year', $year);
    }
}
