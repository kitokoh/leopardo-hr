<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Question de quiz (TRAVEL-904, issue #6107). Bonne réponse stockée HACHÉE, jamais en clair.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $quiz_id
 * @property int $rank
 * @property string $label
 * @property array<string, mixed> $choices
 * @property string $correct_answer_hash
 * @property int $points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
