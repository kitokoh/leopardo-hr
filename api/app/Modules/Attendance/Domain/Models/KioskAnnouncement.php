<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class KioskAnnouncement extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'body',
        'priority',
        'is_active',
        'starts_at',
        'expires_at',
    ];
}

