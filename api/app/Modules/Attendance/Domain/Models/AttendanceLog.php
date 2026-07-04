<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceLog extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'synced_from_offline' => 'boolean',
        'punch_meta' => 'array',
    ];
}

