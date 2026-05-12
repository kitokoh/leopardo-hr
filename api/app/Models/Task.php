<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $title
 * @property string $description
 * @property string|null $created_by
 * @property array<mixed> $assigned_to
 * @property int|null $project_id
 * @property \Illuminate\Support\Carbon $due_date
 * @property string|null $priority
 * @property string $status
 * @property string|null $category
 * @property array<mixed> $checklist
 * @property string|null $visibility
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Task extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'tasks';

    protected $fillable = ['company_id', 'title', 'description', 'created_by', 'assigned_to', 'project_id', 'due_date', 'priority', 'status', 'category', 'checklist', 'visibility'];

    protected $casts = ['assigned_to' => 'array', 'checklist' => 'array', 'due_date' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

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

    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->whereJsonContains('assigned_to', $employeeId);
    }
}
