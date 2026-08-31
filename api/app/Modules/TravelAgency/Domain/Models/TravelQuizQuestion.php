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
use Database\Factories\TravelQuizQuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Question de quiz (TRAVEL-904, issue #6107).
 *
 * `correct_option_index` n'est JAMAIS exposé au participant (Resources
 * distinctes participant / gestion).
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
use Illuminate\Database\Eloquent\Model;

/**
 * Question de quiz (TRAVEL-904, issue #6107). Bonne réponse stockée HACHÉE, jamais en clair.
 * @property int $position
 *
 * @mixin Builder<static>
 */
class TravelQuizQuestion extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelQuizQuestionFactory> */
    use HasFactory;

    protected $table = 'travel_quiz_questions';

    protected $fillable = [
        'company_id',
        'quiz_id',
        'question',
        'options',
        'correct_option_index',
        'points',
        'sort_order',
        'position',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_option_index' => 'integer',
        'points' => 'integer',
        'sort_order' => 'integer',
    protected $fillable = [
        'company_id', 'quiz_id', 'rank', 'label', 'choices', 'correct_answer_hash', 'points',
    ];

    protected $casts = [
        'choices' => 'array',
        'rank' => 'integer',
        'points' => 'integer',
    ];
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelQuiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TravelQuiz::class, 'quiz_id');
    }
}
