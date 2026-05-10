<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
