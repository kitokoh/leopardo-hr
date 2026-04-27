<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'positions';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'name', 'department_id'];
    protected $casts = ['created_at' => 'datetime'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
