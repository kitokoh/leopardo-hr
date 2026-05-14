<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property string $contract_type
 * @property string $reference
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string $job_title
 * @property int|null $department_id
 * @property int|null $position_id
 * @property float $base_salary
 * @property string $currency
 * @property string $salary_frequency
 * @property float $work_hours_per_week
 * @property Carbon|null $probation_end_date
 * @property array<mixed> $benefits
 * @property array<mixed> $clauses
 * @property string $status
 * @property Carbon|null $signed_at
 * @property string|null $signed_document_path
 * @property string|null $termination_reason
 * @property Carbon|null $terminated_at
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /** @return HasMany<ContractAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeExpiringSoon(Builder $q, int $days = 30): Builder
    {
        return $q->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }
}
