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
 * Deux champs de réponse, et la distinction est le cœur du dispositif :
 *  - `answer_draft` — ce que l'IA (ou un rédacteur) propose ;
 *  - `answer_approved` — ce que l'humain a relu, FIGÉ à la validation.
 *
 * `approve()` copie le brouillon dans le figé et horodate l'acte. `publish()`
 * publie le figé, jamais le brouillon : une modification du brouillon après
 * validation ne peut donc pas fuiter en production sans nouvelle validation.
 *
 * @property int $id
 * @property string $question
 * @property string|null $answer_draft
 * @property string|null $answer_approved
 * @property string|null $sector
 * @property string $locale
 * @property AeoQuestionStatus $status
 * @property array<int, mixed>|null $sources
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 *
 * @mixin Builder<static>
 */
class AeoQuestion extends Model
{
    protected $table = 'aeo_questions';

    protected $fillable = [
        'question',
        'answer_draft',
        'sector',
        'locale',
        'sources',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AeoQuestionStatus::class,
            'sources' => 'array',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Soumet le brouillon à la relecture.
     *
     * @throws ReviewRequiredException si le statut ne le permet pas.
     */
    public function submitForReview(): void
    {
        $this->assertTransition(AeoQuestionStatus::InReview);
        $this->status = AeoQuestionStatus::InReview;
    }

    /**
     * ACTE DE VALIDATION HUMAINE : fige le brouillon courant dans
     * `answer_approved` et enregistre qui, quand.
     *
     * @throws ReviewRequiredException
     */
    public function approve(int $approvedBy, ?Carbon $at = null): void
    {
        $this->assertTransition(AeoQuestionStatus::Approved);

        if ($this->answer_draft === null || trim($this->answer_draft) === '') {
            throw new ReviewRequiredException('Impossible de valider une réponse vide.');
        }

        $this->status = AeoQuestionStatus::Approved;
        $this->answer_approved = $this->answer_draft;
        $this->approved_by = $approvedBy;
        $this->approved_at = $at ?? Carbon::now();
    }

    /**
     * Publie la réponse FIGÉE.
     *
     * @throws ReviewRequiredException si la validation humaine manque.
     */
    public function publish(?Carbon $at = null): void
    {
        $this->status->assertPublishable($this->approved_by, $this->answer_approved);

        $this->status = AeoQuestionStatus::Published;
        $this->published_at = $at ?? Carbon::now();
    }

    /** Le contenu publié a vieilli : il sort du public sans être détruit. */
    public function markToUpdate(): void
    {
        $this->assertTransition(AeoQuestionStatus::ToUpdate);
        $this->status = AeoQuestionStatus::ToUpdate;
    }

    /** @throws ReviewRequiredException */
    private function assertTransition(AeoQuestionStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new ReviewRequiredException(sprintf(
                'Transition %s → %s refusée pour la question AEO.',
                $this->status->value,
                $target->value,
            ));
        }
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AeoQuestionStatus::Published->value);
    }
}
