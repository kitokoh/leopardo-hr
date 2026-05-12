<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $applicant_id
 * @property int|null $company_id
 * @property int|null $interviewer_id
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null $duration_minutes
 * @property string $status
 * @property string|null $feedback
 * @property string|null $rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Interview extends Model
{
    use BelongsToCompany;

    protected $table = 'interviews';

    protected $fillable = [
        'applicant_id',
        'company_id',
        'interviewer_id',
        'type',
        'scheduled_at',
        'duration_minutes',
        'status',
        'feedback',
        'rating',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_id');
    }
}
