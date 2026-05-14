<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property int|null $manager_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Employee|null $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Position> $positions
 */
class Department extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'departments';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'manager_id'];

    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<Employee, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }
}
