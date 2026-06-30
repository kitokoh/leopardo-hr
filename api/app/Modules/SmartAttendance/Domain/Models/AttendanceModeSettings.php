<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $company_id
 * @property string|null $forced_mode         null | gps_auto | qr | manual | mixed
 * @property bool        $gps_enabled
 * @property float|null  $latitude
 * @property float|null  $longitude
 * @property int         $radius_meters
 * @property bool        $allow_employee_override
 * @property int|null    $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceModeSettings extends Model
{
    protected $table = 'attendance_mode_settings';

    protected $fillable = [
        'company_id',
        'forced_mode',
        'gps_enabled',
        'latitude',
        'longitude',
        'radius_meters',
        'allow_employee_override',
        'updated_by',
    ];

    protected $casts = [
        'gps_enabled'             => 'boolean',
        'latitude'                => 'float',
        'longitude'               => 'float',
        'radius_meters'           => 'integer',
        'allow_employee_override' => 'boolean',
    ];

    /** Manager/RH qui a effectué la dernière modification */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by');
    }

    /** Retourne true si un mode est imposé à tous les employés */
    public function hasForcedMode(): bool
    {
        return $this->forced_mode !== null;
    }

    /** Retourne le mode effectif (forced si défini, sinon null = choix libre) */
    public function effectiveMode(): ?string
    {
        return $this->forced_mode;
    }
}
