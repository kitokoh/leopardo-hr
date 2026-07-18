<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $location_label
 * @property string $device_code
 * @property string $status
 * @property string $biometric_mode
 * @property string|null $trusted_device_label
 * @property string|null $sync_token_hash
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $last_sync_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceKiosk extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];
}

