<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelQuizFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quiz & jeu-concours (TRAVEL-904, issue #6107).
 *
 * @property int $id
 * @property string $company_id
 * @property string $title
 * @property string|null $description_redacted
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $max_participations_per_contact
 * @property QuizStatus $status
 * @property Carbon|null $published_at
 *
 * @mixin Builder<static>
 *
 * @property int $bonus_points
 */
class TravelQuiz extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelQuizFactory> */
    use HasFactory;

    protected $table = 'travel_quizzes';

    /** Statut d'un quiz encore en brouillon. */
    public const STATUS_DRAFT = 'draft';

    /** Statut d'un quiz ouvert aux participations. */
    public const STATUS_PUBLISHED = 'published';

    /** Statut d'un quiz clôturé (plus de participation acceptée). */
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'title',
        'description_redacted',
        'starts_at',
        'ends_at',
        'max_participations_per_contact',
        'status',
        'published_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_participations_per_contact' => 'integer',
        'status' => QuizStatus::class,
        'published_at' => 'datetime',
    ];

    /**
     * @return HasMany<TravelQuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(TravelQuizQuestion::class, 'quiz_id');
    }

    /**
     * @return HasMany<TravelQuizParticipation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(TravelQuizParticipation::class, 'quiz_id');
    }
}
