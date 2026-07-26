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
}

