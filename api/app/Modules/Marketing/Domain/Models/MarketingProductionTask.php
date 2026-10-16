<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — tâche de production d'un contenu (brief §B, §D).
 *
 * ## Pourquoi une table Marketing et non `tasks` (module Planning)
 *
 * `tasks.created_by` est une clé étrangère **obligatoire** vers `employees`
 * (migration `2026_04_01_000104`). Or l'acteur qui promeut une idée depuis le
 * Super Admin est un compte **plateforme**, sans fiche employé : créer une
 * ligne Planning exigerait soit un employé fictif, soit d'assouplir une
 * contrainte du module Planning. Les deux sont inacceptables.
 *
 * La tâche de production marketing est donc décrite ici, avec sa propre
 * machine d'état, et la chaîne reste tracée sans import croisé de module :
 * `content_ideas.production_task_id` → cette table. Le jour où un acteur
 * employé sera disponible, un adaptateur pourra créer en plus une tâche
 * Planning, sans migration de données ni rupture d'historique.
 *
 * @property int $id
 * @property string $company_id
 * @property int $content_idea_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $due_date
 * @property string $priority
 * @property string $category
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingProductionTask extends Model
{
    use BelongsToCompany;

    protected $table = 'marketing_production_tasks';

    protected $fillable = [
        'company_id',
        'content_idea_id',
        'title',
        'description',
        'due_date',
        'priority',
        'category',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ContentIdea, $this> */
    public function contentIdea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class, 'content_idea_id');
    }
}
