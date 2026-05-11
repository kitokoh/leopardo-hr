<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    use BelongsToCompany;

    protected $table = 'training_courses';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'category',
        'type',
        'provider',
        'duration_hours',
        'max_participants',
        'cost_per_participant',
        'currency',
        'materials_path',
        'active',
    ];

    protected $casts = [
        'duration_hours' => 'float',
        'cost_per_participant' => 'float',
        'active' => 'boolean',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'training_course_id');
    }
}
