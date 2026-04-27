<?php

namespace App\Models\Cameras;

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

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

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
