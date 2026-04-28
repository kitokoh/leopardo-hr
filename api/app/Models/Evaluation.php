<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'evaluations';

    protected $fillable = [
        'company_id', 'employee_id', 'evaluator_id', 'period',
        'score', 'criteria', 'strengths', 'improvements',
        'overall_comment', 'status', 'acknowledged_at',
    ];

    protected $casts = [
        'score' => 'float',
        'criteria' => 'array',
        'acknowledged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_id');
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }

    public function scopeSubmitted(Builder $q): Builder
    {
        return $q->where('status', 'submitted');
    }

    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
