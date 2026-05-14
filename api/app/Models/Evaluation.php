<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property int|null $evaluator_id
 * @property string|null $period
 * @property float $score
 * @property array<mixed> $criteria
 * @property string|null $strengths
 * @property string|null $improvements
 * @property string|null $overall_comment
 * @property string $status
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Employee|null $evaluator
 */
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

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeSubmitted(Builder $q): Builder
    {
        return $q->where('status', 'submitted');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $id): Builder
    {
        return $q->where('employee_id', $id);
    }
}
