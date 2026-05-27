<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $title
 * @property string $description
 * @property string|null $created_by
 * @property array<mixed> $assigned_to
 * @property int|null $project_id
 * @property Carbon $due_date
 * @property string|null $priority
 * @property int|null $estimated_minutes
 * @property int|null $completed_minutes
 * @property Carbon|null $completed_at
 * @property string|null $completion_note
 * @property string|null $performance_score
 * @property string|null $recurrence_rule
 * @property string|null $template_key
 * @property string $status
 * @property string|null $category
 * @property array<mixed> $checklist
 * @property string|null $visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TaskComment> $comments
 */
class Task extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'tasks';

    protected $fillable = ['company_id', 'title', 'description', 'created_by', 'assigned_to', 'project_id', 'due_date', 'priority', 'estimated_minutes', 'completed_minutes', 'completed_at', 'completion_note', 'performance_score', 'recurrence_rule', 'template_key', 'status', 'category', 'checklist', 'visibility'];

    protected $casts = ['assigned_to' => 'array', 'checklist' => 'array', 'due_date' => 'datetime', 'completed_at' => 'datetime', 'performance_score' => 'decimal:2', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->whereJsonContains('assigned_to', $employeeId);
    }
}
