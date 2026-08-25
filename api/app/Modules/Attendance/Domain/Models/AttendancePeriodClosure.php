<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Issue #5267 — clôture tracée d'une période de pointage (verrouillage des
 * corrections après clôture).
 *
 * @property int $id
 * @property string $company_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property-read Employee|null $closer
 *
 * @mixin Builder<static>
 */
class AttendancePeriodClosure extends Model
{
    use BelongsToCompany;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'attendance_period_closures';

    protected $fillable = [
        'company_id',
        'period_start',
        'period_end',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'closed_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'closed_by');
    }

    /** La date est-elle couverte par cette clôture ? */
    public function covers(Carbon $date): bool
    {
        return $date->between($this->period_start, $this->period_end);
    }
}
