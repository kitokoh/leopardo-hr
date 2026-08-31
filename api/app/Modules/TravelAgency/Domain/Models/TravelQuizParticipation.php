<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-904 (#6107) — Participation à un quiz (unique par tenant, quiz et
 * participant — la participation est bornée par `max_attempts` du quiz).
use Database\Factories\TravelQuizParticipationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participation à un quiz (TRAVEL-904, issue #6107).
 *
 * Réponses et score calculés serveur ; participation unique par
 * (quiz, email) — contrainte en base.
 *
 * @property int $id
 * @property string $company_id
 * @property int $quiz_id
 * @property string $participant_type
 * @property int $participant_id
 * @property array<int, int> $answers
 * @property int $score
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 *
 * @mixin Builder<static>
use Illuminate\Database\Eloquent\Model;

/**
 * Participation à un quiz (TRAVEL-904, issue #6107). Participation UNIQUE par (quiz, contact).
 * @property int|null $participant_contact_id
 * @property string|null $participant_email
 * @property string|null $participant_name
 * @property array<int, array<string, mixed>> $answers
 * @property int $score
 * @property int $bonus
 * @property string $status
 *
 * @mixin Builder<static>
 */
class TravelQuizParticipation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelQuizParticipationFactory> */
    use HasFactory;

    protected $table = 'travel_quiz_participations';

    protected $fillable = [
        'company_id',
        'quiz_id',
        'participant_type',
        'participant_id',
        'answers',
        'score',
        'status',
        'completed_at',
        'participant_contact_id',
        'participant_email',
        'participant_name',
        'answers',
        'score',
        'bonus',
        'status',
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
    protected $fillable = [
        'company_id', 'quiz_id', 'participant_identifier', 'answers_redacted', 'score', 'total_points', 'completed_at',
    ];

    protected $casts = [
        'answers_redacted' => 'array',
        'score' => 'integer',
        'total_points' => 'integer',
        'completed_at' => 'datetime',
    ];
        'bonus' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelQuiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TravelQuiz::class, 'quiz_id');
    }
}
