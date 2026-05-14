<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property int|null $department_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Department|null $department
 */
class Position extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'positions';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'department_id'];

    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
