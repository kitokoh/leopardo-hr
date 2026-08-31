<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quiz du tenant (TRAVEL-904, issue #6107). Statut draft|published|closed, bornes de participation.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $title
 * @property string $description_redacted
 * @property string $status
 * @property int $max_participations_per_contact
 * @property int $bonus_points
 * @property string $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelQuiz extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'title', 'description_redacted', 'status', 'max_participations_per_contact', 'bonus_points', 'created_by_user_id',
    ];

    protected $casts = [
        'max_participations_per_contact' => 'integer',
        'bonus_points' => 'integer',
    ];

    /**
     * @return HasMany<TravelQuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(TravelQuizQuestion::class, 'quiz_id');
    }
}
