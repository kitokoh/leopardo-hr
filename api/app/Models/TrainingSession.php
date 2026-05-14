<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $training_course_id
 * @property int|null $company_id
 * @property int|null $trainer_id
 * @property string|null $external_trainer
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string $location
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TrainingSession extends Model
{
    use BelongsToCompany;

    protected $table = 'training_sessions';

    protected $fillable = [
        'training_course_id',
        'company_id',
        'trainer_id',
        'external_trainer',
        'start_date',
        'end_date',
        'location',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** @return BelongsTo<TrainingCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'trainer_id');
    }

    /** @return HasMany<TrainingEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'training_session_id');
    }
}
