<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZktecoSyncLog extends Model
{
    protected $table = 'zkteco_sync_logs';

    protected $fillable = [
        'zkteco_device_id',
        'direction',
        'sync_type',
        'records_count',
        'errors_count',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'records_count' => 'integer',
        'errors_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktecoDevice::class, 'zkteco_device_id');
    }
}
