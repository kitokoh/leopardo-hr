<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 * @property string|null $icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CabinetFolder> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, CabinetDocument> $documents
 * @property-read int|null $documents_count
 */
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
