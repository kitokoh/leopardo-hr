<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use BelongsToCompany;

    protected $table = 'contracts';

    protected $fillable = [
        'company_id',
        'employee_id',
        'contract_type',
        'reference',
        'start_date',
        'end_date',
        'job_title',
        'department_id',
        'position_id',
        'base_salary',
        'currency',
        'salary_frequency',
        'work_hours_per_week',
        'probation_end_date',
        'benefits',
        'clauses',
        'status',
        'signed_at',
        'signed_document_path',
        'termination_reason',
        'terminated_at',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'probation_end_date' => 'date',
        'base_salary' => 'float',
        'work_hours_per_week' => 'float',
        'benefits' => 'array',
        'clauses' => 'array',
        'signed_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeExpiringSoon(Builder $q, int $days = 30): Builder
    {
        return $q->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }
}
