<?php

namespace App\Models\Cameras;

use App\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token d'accès délégué à un tiers externe (sans compte) ou à un utilisateur
 * interne hors-app. Vérifié par MediaMTX via l'endpoint interne.
 *
 * Section 4.2 du cahier des charges.
 */
class CameraAccessToken extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'camera_access_tokens';

    protected $fillable = [
        'company_id',
        'camera_id',
        'token',
        'label',
        'granted_to_email',
        'granted_to_name',
        'granted_by',
        'permissions',
        'expires_at',
        'last_used_at',
        'use_count',
        'is_revoked',
        'ip_whitelist',
    ];

    protected $casts = [
        'permissions' => 'array',
        'ip_whitelist' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'use_count' => 'integer',
        'is_revoked' => 'boolean',
    ];

    protected $attributes = [
        'is_revoked' => false,
        'use_count' => 0,
        'permissions' => '{"view":true}',
    ];

    protected $hidden = [
        'token',
    ];

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'granted_by');
    }

    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isFuture();
    }

    public function expirationReason(): ?string
    {
        if ($this->is_revoked) {
            return 'token_revoked';
        }

        if ($this->expires_at === null || $this->expires_at->isPast()) {
            return 'token_expired';
        }

        return null;
    }
}
