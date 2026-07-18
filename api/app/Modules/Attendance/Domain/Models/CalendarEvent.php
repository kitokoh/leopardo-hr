<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property bool $all_day
 * @property string $title
 * @property string|null $description
 * @property string|null $external_event_id
 * @property string|null $sync_status
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CalendarEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
    ];
}

