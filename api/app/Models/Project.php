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
 * @property string $name
 * @property string $description
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property array<mixed> $members
 * @property string $status
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Project extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'projects';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'description', 'start_date', 'end_date', 'members', 'status', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'members' => 'array', 'created_at' => 'datetime'];

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
