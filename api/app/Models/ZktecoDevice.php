<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZktecoDevice extends Model
{
    protected $table = 'zkteco_devices';

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
        'last_heartbeat_at',
        'last_sync_at',
        'capabilities',
    ];

    protected $casts = [
        'port' => 'integer',
        'employee_capacity' => 'integer',
        'fingerprint_capacity' => 'integer',
        'face_capacity' => 'integer',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'capabilities' => 'array',
    ];

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ZktecoSyncLog::class, 'zkteco_device_id');
    }

    public function isOnline(): bool
    {
        return $this->status === 'online'
            && $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->diffInMinutes(now()) < 5;
    }
}
