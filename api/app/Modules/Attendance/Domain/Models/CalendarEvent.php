<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
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
    use BelongsToCompany;
    protected $fillable = [
        'company_id',
        'employee_id',
        'external_event_id',
        'provider',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'all_day',
        'source_type',
        'source_id',
        'sync_status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
    ];
}

