<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Participation à un quiz (TRAVEL-904, issue #6107). Participation UNIQUE par (quiz, contact).
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
