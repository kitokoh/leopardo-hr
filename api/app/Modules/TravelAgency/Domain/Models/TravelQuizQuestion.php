<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Question de quiz (TRAVEL-904, issue #6107). Bonne réponse stockée HACHÉE, jamais en clair.
 */
class TravelQuizQuestion extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'quiz_id', 'rank', 'label', 'choices', 'correct_answer_hash', 'points',
    ];

    protected $casts = [
        'choices' => 'array',
        'rank' => 'integer',
        'points' => 'integer',
    ];
}
