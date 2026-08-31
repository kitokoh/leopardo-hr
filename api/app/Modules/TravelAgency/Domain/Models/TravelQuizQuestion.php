<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-904 (#6107) — Question d'un quiz.
 *
 * La bonne réponse (`correct_option_index`) n'est JAMAIS exposée dans les
 * réponses API publiques : le score est calculé serveur.
 *
 * @property int $id
 * @property string $company_id
 * @property int $quiz_id
 * @property string $question
 * @property array<int, string> $options
 * @property int $correct_option_index
 * @property int $points
 * @property int $sort_order
 *
 * @mixin Builder<static>
 */
class TravelQuizQuestion extends Model
{
    use BelongsToCompany;

    protected $table = 'travel_quiz_questions';

    protected $fillable = [
        'company_id',
        'quiz_id',
        'question',
        'options',
        'correct_option_index',
        'points',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option_index' => 'integer',
        'points' => 'integer',
        'sort_order' => 'integer',
    ];
}
