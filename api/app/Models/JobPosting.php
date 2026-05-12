<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $title
 * @property string $description
 * @property int|null $department_id
 * @property int|null $position_id
 * @property string $location
 * @property string $remote_policy
 * @property string $contract_type
 * @property float $salary_range_min
 * @property float $salary_range_max
 * @property string $currency
 * @property array<mixed> $skills_required
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $closes_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class JobPosting extends Model
{
    use BelongsToCompany;

    protected $table = 'job_postings';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'department_id',
        'position_id',
        'location',
        'remote_policy',
        'contract_type',
        'salary_range_min',
        'salary_range_max',
        'currency',
        'skills_required',
        'status',
        'published_at',
        'closes_at',
        'created_by',
    ];

    protected $casts = [
        'salary_range_min' => 'float',
        'salary_range_max' => 'float',
        'skills_required' => 'array',
        'published_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

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

    /** @return HasMany<Applicant, $this> */
    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class, 'job_posting_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }
}
