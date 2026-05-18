<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarConnection extends Model
{
    protected $fillable = [
        'employee_id',
        'provider',
        'access_token',
        'refresh_token',
        'calendar_id',
        'token_expires_at',
        'sync_leaves',
        'sync_training',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'sync_leaves' => 'boolean',
        'sync_training' => 'boolean',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
