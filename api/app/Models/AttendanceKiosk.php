<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceKiosk extends Model
{
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
