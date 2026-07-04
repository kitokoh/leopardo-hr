<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AttendanceKiosk extends Model
{
    use BelongsToCompany;

    protected $guarded = [];
}

