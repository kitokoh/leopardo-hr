<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CabinetFolder extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'cabinet_folders';

    protected $fillable = [
        'company_id',
        'employee_id',
        'parent_id',
        'name',
        'color',
        'icon',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CabinetDocument::class, 'folder_id');
    }

    public function shares(): MorphMany
    {
        return $this->morphMany(CabinetShare::class, 'shareable');
    }
}
