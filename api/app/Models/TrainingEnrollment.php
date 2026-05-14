<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $training_session_id
 * @property int|null $employee_id
 * @property int|null $company_id
 * @property string $status
 * @property float $score
 * @property string|null $certificate_path
 * @property string|null $feedback
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TrainingEnrollment extends Model
{
    use BelongsToCompany;

    protected $table = 'training_enrollments';

    protected $fillable = [
        'training_session_id',
        'employee_id',
        'company_id',
        'status',
        'score',
        'certificate_path',
        'feedback',
        'completed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<TrainingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
