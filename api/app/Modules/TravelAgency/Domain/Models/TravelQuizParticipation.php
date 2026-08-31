<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Participation à un quiz (TRAVEL-904, issue #6107). Participation UNIQUE par (quiz, contact).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $quiz_id
 * @property string $participant_identifier
 * @property array<string, mixed> $answers_redacted
 * @property int $score
 * @property int $total_points
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelQuizParticipation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'quiz_id', 'participant_identifier', 'answers_redacted', 'score', 'total_points', 'completed_at',
    ];

    protected $casts = [
        'answers_redacted' => 'array',
        'score' => 'integer',
        'total_points' => 'integer',
        'completed_at' => 'datetime',
    ];
}
