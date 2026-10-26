<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-2 (#8513) — mot-clé cible du registre.
 *
 * L'unicité porte sur (phrase, locale) : la même expression dans deux langues
 * est deux cibles distinctes.
 *
 * `data_source` + `last_measured_at` ne sont pas décoratifs : un volume ou une
 * position sans provenance ni date de mesure est un chiffre invérifiable, ce
 * que la spec interdit explicitement.
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
 * @property int|null $target_page_id
 * @property int|null $impressions
 * @property int|null $clicks
 * @property string $data_source
 * @property Carbon|null $last_measured_at
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
        'target_page_id',
        'impressions',
        'clicks',
        'data_source',
        'last_measured_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'volume' => 'integer',
            'difficulty' => 'integer',
            'current_rank' => 'integer',
            'target_page_id' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'last_measured_at' => 'datetime',
        ];
    }

    /**
     * Une mesure est-elle exploitable ? Sans date, un rang est un souvenir :
     * la vue de pilotage doit pouvoir distinguer une mesure récente d'une
     * donnée périmée.
     */
    public function hasFreshMeasurement(int $days = 30): bool
    {
        if ($this->last_measured_at === null) {
            return false;
        }

        return $this->last_measured_at->greaterThanOrEqualTo(Carbon::now()->subDays($days));
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
