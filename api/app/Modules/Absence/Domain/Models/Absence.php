<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain model for the Absence aggregate.
 *
 * Colonnes reelles de la table `absences`
 * (migration tenant 2026_04_01_000103_create_attendance_absences_advances.php).
 *
 * @property int    $id
 * @property int|null $company_id
 * @property int    $employee_id
 * @property int    $absence_type_id
 * @property string $start_date
 * @property string $end_date
 * @property int    $days_count
 * @property string $status  (pending|approved|rejected|cancelled)
 * @property string|null $reason
 * @property string|null $proof_path
 * @property int|null $approved_by
 * @property string|null $rejected_reason
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Absence extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'absence_type_id',
        'start_date',
        'end_date',
        'days_count',
        'status',
        'reason',
        'proof_path',
        'approved_by',
        'rejected_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function absenceType(): BelongsTo
    {
        return $this->belongsTo(AbsenceType::class);
    }
}
