<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $auditable_type
 * @property string $auditable_id
 * @property string $event
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $reason
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class PartnerAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
