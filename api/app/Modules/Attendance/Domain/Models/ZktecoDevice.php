<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 */
class ZktecoDevice extends Model
{
    protected $fillable = [
        'company_id',
        'serial_number',
        'sync_token_hash',
        'name',
        'ip_address',
        'port',
        'protocol',
        'location_label',
        'status',
        'model',
        'firmware_version',
        'employee_capacity',
        'fingerprint_capacity',
        'face_capacity',
        'capabilities',
        'last_heartbeat_at',
        'last_sync_at',
    ];

    /**
     * Le hash du token de sync ne doit JAMAIS sortir de l'API.
     */
    protected $hidden = [
        'sync_token_hash',
    ];

    protected $casts = [
        'port' => 'integer',
        'employee_capacity' => 'integer',
        'fingerprint_capacity' => 'integer',
        'face_capacity' => 'integer',
        'capabilities' => 'array',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Société propriétaire du dispositif (#4787).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
