<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
     * Issue #2216 — le hash du token ne doit JAMAIS être exposé par l'API
     * (index/show/update) : seul le client device le connaît (token brut
     * renvoyé une seule fois à la création).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'sync_token_hash',
    ];
}

