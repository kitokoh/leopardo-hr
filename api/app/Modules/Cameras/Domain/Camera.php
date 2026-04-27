<?php

namespace App\Models\Cameras;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(CameraAccessToken::class, 'camera_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(CameraPermission::class, 'camera_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(CameraAccessLog::class, 'camera_id');
    }
}
