<?php

namespace App\Modules\Cameras\Domain;

use App\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission interne accordée à un employé sur une caméra spécifique.
 * Section 4.3 du cahier des charges.
 *
 * Contrainte unique : (camera_id, employee_id) — une seule ligne par couple.
 *
 * @property int $id
 * @property int $company_id
 * @property int $camera_id
 * @property int $employee_id
 * @property bool $can_view
 * @property bool $can_share
 * @property bool $can_manage
 * @property int|null $granted_by
 * @property \Illuminate\Support\Carbon|null $granted_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Cameras\Domain\Camera|null $camera
 * @property-read \App\Models\Employee|null $employee
 * @property-read \App\Models\Employee|null $grantor
 */
class CameraPermission extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'camera_permissions';

    protected $fillable = [
        'company_id',
        'camera_id',
        'employee_id',
        'can_view',
        'can_share',
        'can_manage',
        'granted_by',
        'granted_at',
        'expires_at',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_share' => 'boolean',
        'can_manage' => 'boolean',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'can_view' => true,
        'can_share' => false,
        'can_manage' => false,
    ];

    /** @return BelongsTo<Camera, $this> */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'granted_by');
    }

    public function isActive(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }
}
