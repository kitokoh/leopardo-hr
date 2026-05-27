<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $folder_id
 * @property string $name
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CabinetDocument extends Model
{
    protected $table = 'cabinet_documents';

    protected $fillable = [
        'company_id',
        'employee_id',
        'folder_id',
        'name',
        'original_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'size' => 'integer',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<CabinetFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CabinetFolder::class, 'folder_id');
    }

    /**
     * @return MorphMany<CabinetShare, $this>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(CabinetShare::class, 'shareable');
    }
}
