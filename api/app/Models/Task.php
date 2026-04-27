<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'tasks';

    protected $fillable = ['company_id', 'title', 'description', 'created_by', 'assigned_to', 'project_id', 'due_date', 'priority', 'status', 'category', 'checklist', 'visibility'];
    protected $casts = ['assigned_to' => 'array', 'checklist' => 'array', 'due_date' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function creator(): BelongsTo { return $this->belongsTo(Employee::class, 'created_by'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'project_id'); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class, 'task_id'); }
    public function scopeForEmployee(Builder $q, int $id): Builder { return $q->whereJsonContains('assigned_to', $id); }
}
