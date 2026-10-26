<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * GSI-2 (#8513) — mot-clé cible du registre.
 *
 * L'unicité porte sur (phrase, locale) : la même expression dans deux langues
 * est deux cibles distinctes.
 *
 * @property int $id
 * @property string $phrase
 * @property string $locale
 * @property string $intent
 * @property int $priority
 * @property int|null $volume
 * @property int|null $difficulty
 * @property int|null $current_rank
 * @property string|null $target_url
 *
 * @mixin Builder<static>
 */
class Keyword extends Model
{
    protected $table = 'keywords';

    protected $fillable = [
        'phrase',
        'locale',
        'intent',
        'priority',
        'volume',
        'difficulty',
        'current_rank',
        'target_url',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'volume' => 'integer',
            'difficulty' => 'integer',
            'current_rank' => 'integer',
        ];
    }

    /**
     * Priorité 1 = le plus important. Le tri par `priority` croissante puis
     * volume décroissant est la vue par défaut du registre.
     *
     * @param  Builder<self>  $query
     */
    public function scopeHighPriority(Builder $query): void
    {
        $query->where('priority', '<=', 2)->orderBy('priority');
    }
}
