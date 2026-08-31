<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-904 (#6107) — Participation à un quiz (unique par tenant, quiz et
 * participant — la participation est bornée par `max_attempts` du quiz).
 *
 * @property int $id
 * @property string $company_id
 * @property int $quiz_id
 * @property string $participant_type
 * @property int $participant_id
 * @property array<int, int> $answers
 * @property int $score
 * @property string $status
 * @property Carbon|null $completed_at
 *
 * @mixin Builder<static>
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $quiz_id
 * @property string $participant_type
 * @property string $participant_id
 * @property array<string, mixed> $answers
 * @property int $score
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelQuizParticipation extends Model
{
    use BelongsToCompany;

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
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'integer',
        'completed_at' => 'datetime',
    ];
}
