<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class, 'job_posting_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }
}
