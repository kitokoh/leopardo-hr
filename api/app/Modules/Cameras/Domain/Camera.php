<?php

namespace App\Modules\Cameras\Domain;

use App\Models\Company;
use App\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Caméra IP déclarée par une company (module Surveillance Caméras).
 *
 * Section 4.1 du cahier des charges : rtsp_url est chiffré en base via le
 * cast Laravel "encrypted" (AES-256 dérivé d'APP_KEY).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $rtsp_url
 * @property string|null $location
 * @property bool $is_active
 * @property string|null $thumbnail_path
 * @property int $sort_order
 * @property int|null $created_by
 * @property string|null $stream_path_override
 * @property array<mixed> $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Employee|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Cameras\Domain\CameraAccessToken> $accessTokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Cameras\Domain\CameraPermission> $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Cameras\Domain\CameraAccessLog> $accessLogs
 */
class Camera extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cameras';

    protected $fillable = [
        'company_id',
        'name',
        'rtsp_url',
        'location',
        'is_active',
        'thumbnail_path',
        'sort_order',
        'created_by',
        'stream_path_override',
        'metadata',
    ];

    protected $casts = [
        'rtsp_url' => 'encrypted',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'metadata' => '{}',
    ];

    protected $hidden = [
        'rtsp_url',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /** @return HasMany<CameraAccessToken, $this> */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(CameraAccessToken::class, 'camera_id');
    }

    /** @return HasMany<CameraPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(CameraPermission::class, 'camera_id');
    }

    /** @return HasMany<CameraAccessLog, $this> */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(CameraAccessLog::class, 'camera_id');
    }
}
