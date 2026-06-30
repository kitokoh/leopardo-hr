<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property int         $employee_id
 * @property string      $company_id
 * @property int|null    $geo_session_id
 * @property string      $event_type
 * @property float|null  $latitude
 * @property float|null  $longitude
 * @property int|null    $accuracy_meters
 * @property Carbon|null $device_timestamp
 * @property array       $metadata
 * @property Carbon      $created_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class EmployeeLocationEvent extends Model
{
    protected $table = 'employee_location_events';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    // Types d'événements supportés
    public const TYPE_ZONE_ENTER       = 'zone_enter';
    public const TYPE_ZONE_EXIT        = 'zone_exit';
    public const TYPE_SESSION_START    = 'session_start';
    public const TYPE_SESSION_END      = 'session_end';
    public const TYPE_CONSENT_GIVEN    = 'consent_given';
    public const TYPE_CONSENT_REVOKED  = 'consent_revoked';
    public const TYPE_GEOFENCE_ERROR   = 'geofence_error';
    public const TYPE_OUTSIDE_ZONE     = 'outside_zone';

    protected $fillable = [
        'employee_id',
        'company_id',
        'geo_session_id',
        'event_type',
        'latitude',
        'longitude',
        'accuracy_meters',
        'device_timestamp',
        'metadata',
    ];

    protected $casts = [
        'latitude'         => 'float',
        'longitude'        => 'float',
        'device_timestamp' => 'datetime',
        'metadata'         => 'array',
        'created_at'       => 'datetime',
    ];

    protected $attributes = [
        'metadata' => '{}',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function geoSession(): BelongsTo
    {
        return $this->belongsTo(GeoAttendanceSession::class, 'geo_session_id');
    }
}
