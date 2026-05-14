<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $title
 * @property string $description
 * @property string|null $category
 * @property string $type
 * @property string $provider
 * @property float $duration_hours
 * @property string|null $max_participants
 * @property float $cost_per_participant
 * @property string $currency
 * @property string|null $materials_path
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return HasMany<TrainingSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'training_course_id');
    }
}
