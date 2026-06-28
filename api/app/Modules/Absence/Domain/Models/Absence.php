<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain model for the Absence aggregate.
 *
 * @property int    $id
 * @property int    $employee_id
 * @property int    $absence_type_id
 * @property string $start_date
 * @property string $end_date
 * @property string $status  (pending|approved|rejected|cancelled)
 * @property string|null $reason
 * @property string|null $manager_comment
 * @property string|null $approved_at
 */
class Absence extends Model
{
    protected $fillable = [
        'employee_id',
        'absence_type_id',
        'start_date',
        'end_date',
        'status',
        'reason',
        'manager_comment',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(\App\Core\Auth\Domain\Models\Employee::class);
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }
}
