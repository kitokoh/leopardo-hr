<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\AeoQuestionStatus;
use App\Modules\SearchIntelligence\Domain\Exceptions\ReviewRequiredException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-4 (#8515) — question/réponse AEO avec révision humaine obligatoire.
 *
 * `publish()` est le SEUL chemin de publication côté application : il vérifie
 * la machine à états et la présence des preuves de révision, puis renseigne
 * `published_at`. La contrainte CHECK de la table double la règle pour les
 * écritures qui contourneraient le modèle.
 *
 * @property int $id
 * @property string $question
 * @property string|null $answer
 * @property string $locale
 * @property AeoQuestionStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AeoQuestion extends Model
{
    protected $table = 'aeo_questions';

    protected $fillable = [
        'question',
        'answer',
        'locale',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AeoQuestionStatus::class,
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Enregistre la révision humaine : c'est cet acte (qui + quand) qui
     * autorise ensuite la publication.
     */
    public function markReviewed(int $reviewedBy, ?Carbon $at = null): void
    {
        $this->status = AeoQuestionStatus::Reviewed;
        $this->reviewed_by = $reviewedBy;
        $this->reviewed_at = $at ?? Carbon::now();
    }

    /**
     * Publie la réponse.
     *
     * @throws ReviewRequiredException si la révision humaine manque ou si la
     *         machine à états refuse la transition.
     */
    public function publish(?Carbon $at = null): void
    {
        $this->status->assertPublishable($this->reviewed_by, $this->answer);

        $this->status = AeoQuestionStatus::Published;
        $this->published_at = $at ?? Carbon::now();
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AeoQuestionStatus::Published->value);
    }
}
