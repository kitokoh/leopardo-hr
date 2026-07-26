<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ZktecoSyncLog extends Model
{
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
}

