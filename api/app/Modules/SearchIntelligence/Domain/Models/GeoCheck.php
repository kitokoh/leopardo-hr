<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\GeoCheckMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * GSI-5 (#8516) — check de présence d'une requête dans un moteur génératif.
 *
 * Trois garanties :
 *  - **méthode obligatoire** et limitée aux trois niveaux de preuve connus
 *    (`GeoCheckMethod`) — la base refuse toute autre valeur ;
 *  - **historique immuable** : un check ne se modifie pas, il s'ajoute. Le
 *    modèle refuse `update()` sur un enregistrement existant, ce qui garantit
 *    que l'historique d'une requête reste une suite d'observations datées et
 *    non un état écrasable ;
 *  - **preuve requise** pour un test ponctuel : `evidence` ou `observed_text`.
 *
 * @property int $id
 * @property int $geo_query_id
 * @property string $engine
 * @property GeoCheckMethod $method
 * @property bool $is_present
 * @property bool $leopardo_mentioned
 * @property int|null $position
 * @property string|null $citation_url
 * @property string|null $observed_text
 * @property array<int, mixed>|null $sources
 * @property string|null $evidence
 * @property int|null $checked_by
 * @property Carbon $observed_at
 *
 * @mixin Builder<static>
 */
class GeoCheck extends Model
{
    protected $table = 'geo_checks';

    protected $fillable = [
        'geo_query_id',
        'engine',
        'method',
        'is_present',
        'leopardo_mentioned',
        'position',
        'citation_url',
        'observed_text',
        'sources',
        'evidence',
        'checked_by',
        'observed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => GeoCheckMethod::class,
            'is_present' => 'boolean',
            'leopardo_mentioned' => 'boolean',
            'position' => 'integer',
            'sources' => 'array',
            'checked_by' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    /**
     * Historique immuable : un check est une OBSERVATION datée. La modifier
     * reviendrait à réécrire le passé — on ajoute un nouveau check.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException(
                'Un check GEO est immuable : ajoutez une nouvelle observation au lieu de modifier celle-ci.'
            );
        }

        return parent::update($attributes, $options);
    }

    /** @return BelongsTo<GeoQuery, $this> */
    public function geoQuery(): BelongsTo
    {
        return $this->belongsTo(GeoQuery::class, 'geo_query_id');
    }

    /**
     * Le check est-il recevable ? Un test ponctuel sans trace textuelle ni
     * preuve est une affirmation invérifiable.
     */
    public function isVerifiable(): bool
    {
        if ($this->method !== GeoCheckMethod::SpotTest) {
            return true;
        }

        return ($this->evidence !== null && trim($this->evidence) !== '')
            || ($this->observed_text !== null && trim((string) $this->observed_text) !== '');
    }

    /** Badge à afficher : le niveau de preuve, jamais masqué. */
    public function methodBadge(): string
    {
        return $this->method->badge();
    }

    /** @param  Builder<self>  $query */
    public function scopePresent(Builder $query): void
    {
        $query->where('is_present', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeMeasured(Builder $query): void
    {
        // Seules ces deux méthodes autorisent une conclusion chiffrée.
        $query->whereIn('method', [
            GeoCheckMethod::ObservedData->value,
            GeoCheckMethod::MeasuredIndicator->value,
        ]);
    }
}
