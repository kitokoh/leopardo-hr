<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CabinetFolder extends Model
{
    use BelongsToCompany;

    protected $table = 'cabinet_folders';

    protected $fillable = [
        'company_id',
        'employee_id',
        'parent_id',
        'name',
        'color',
        'icon',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CabinetDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CabinetDocument::class, 'folder_id');
    }

    /**
     * @return MorphMany<CabinetShare, $this>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(CabinetShare::class, 'shareable');
    }
}
