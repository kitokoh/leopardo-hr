<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $location_label
 * @property string $device_code
 * @property string|null $sync_token_hash
 * @property string $status
 * @property string $biometric_mode
 * @property string|null $trusted_device_label
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 */
class AttendanceKiosk extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'attendance_kiosks';

    protected $fillable = [
        'company_id',
        'name',
        'location_label',
        'device_code',
        'sync_token_hash',
        'status',
        'biometric_mode',
        'trusted_device_label',
        'last_seen_at',
        'last_sync_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
