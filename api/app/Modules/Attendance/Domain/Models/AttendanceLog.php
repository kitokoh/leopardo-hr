<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceLog extends Model
{
    use HasFactory;

    protected $guarded = [];
}

