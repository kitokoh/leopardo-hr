<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-6 (#8517) — contenu marketing à statuts contrôlés.
 *
 * `publish()` n'est atteignable que depuis `approved` et exige une
 * `target_url` : publier sans URL, c'est publier nulle part. La règle est ici
 * ET en base (contrainte CHECK de `2026_10_26_000005`).
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string $kind
 * @property ContentStatus $status
 * @property string|null $body
 * @property string $locale
 * @property string|null $sector
 * @property string|null $target_url
 * @property int|null $author_id
 * @property int|null $keyword_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 *
 * @mixin Builder<static>
 */
class ContentItem extends Model
{
    protected $table = 'content_items';

    protected $fillable = [
        'title',
        'slug',
        'kind',
        'status',
        'body',
        'locale',
        'sector',
        'target_url',
        'keyword_id',
        'scheduled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'author_id' => 'integer',
            'keyword_id' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Transition de statut. Retourne `false` plutôt que de lever : le
     * contrôleur décide du code HTTP, le modèle reste une primitive.
     */
    public function transitionTo(ContentStatus $target, ?Carbon $at = null): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            return false;
        }

        // Publier sans URL est refusé : la contrainte de base le refuserait
        // aussi, mais autant échouer ici avec un message exploitable.
        if ($target === ContentStatus::Published && ($this->target_url === null || trim($this->target_url) === '')) {
            return false;
        }

        $this->status = $target;

        if ($target === ContentStatus::Approved) {
            $this->approved_at = $at ?? Carbon::now();
        }

        if ($target === ContentStatus::Published) {
            $this->published_at = $at ?? Carbon::now();
            $this->approved_at ??= Carbon::now();
        }

        return true;
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published->value);
    }
}
