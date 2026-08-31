<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours (tenant-scoped).
 *
 * @property int $id
 * @property string $company_id
 * @property string $title
 * @property string|null $description_redacted
 * @property string $status
 * @property int $max_attempts
 * @property \Illuminate\Support\Carbon|null $published_at
 *
 * @mixin Builder<static>
 */
class TravelQuiz extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'travel_quizzes';

    protected $fillable = [
        'company_id',
        'title',
        'description_redacted',
        'status',
        'max_attempts',
        'published_at',
    ];

    protected $casts = [
        'max_attempts' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * @return HasMany<TravelQuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(TravelQuizQuestion::class, 'quiz_id')->orderBy('sort_order');
    }
}
