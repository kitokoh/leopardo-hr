<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'departments';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'manager_id'];
    protected $casts = ['created_at' => 'datetime'];

    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function positions(): HasMany { return $this->hasMany(Position::class, 'department_id'); }
}
