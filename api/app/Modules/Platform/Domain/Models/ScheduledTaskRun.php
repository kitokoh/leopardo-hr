<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * PA2-QA-006 — Last known outcome of a scheduled Artisan command.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $runtime_ms
 * @property string $status
 * @property int|null $exit_code
 * @property string|null $output
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScheduledTaskRun extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    protected $table = 'scheduled_task_runs';

    protected $fillable = [
        'name',
        'started_at',
        'finished_at',
        'runtime_ms',
        'status',
        'exit_code',
        'output',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'runtime_ms' => 'integer',
        'exit_code' => 'integer',
    ];
}
