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
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string $kind
 * @property ContentStatus $status
 * @property string|null $body
 * @property string $locale
 * @property int|null $author_id
 * @property int|null $keyword_id
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
        'author_id',
        'keyword_id',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'author_id' => 'integer',
            'keyword_id' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Transition de statut : refuse tout saut non prévu par la machine à
     * états. Retourne `false` plutôt que de lever, le contrôleur décidant du
     * code HTTP.
     */
    public function transitionTo(ContentStatus $target, ?Carbon $at = null): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            return false;
        }

        $this->status = $target;

        if ($target === ContentStatus::Published) {
            $this->published_at = $at ?? Carbon::now();
        }

        return true;
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published->value);
    }
}
