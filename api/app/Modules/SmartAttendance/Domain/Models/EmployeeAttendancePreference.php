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
 * @property string      $preferred_mode      gps_auto | qr | manual
 * @property bool        $gps_consent_given
 * @property Carbon|null $gps_consent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class EmployeeAttendancePreference extends Model
{
    protected $table = 'employee_attendance_preferences';

    protected $fillable = [
        'employee_id',
        'company_id',
        'preferred_mode',
        'gps_consent_given',
        'gps_consent_at',
    ];

    protected $casts = [
        'gps_consent_given' => 'boolean',
        'gps_consent_at'    => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** Vérifie si l'employé a donné son consentement GPS */
    public function hasGpsConsent(): bool
    {
        return $this->gps_consent_given && $this->gps_consent_at !== null;
    }
}
