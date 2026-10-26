<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * GSI-5 (#8516) — check de présence d'une requête dans un moteur génératif.
 *
 * Chaque check porte sa MÉTHODE (`manual` / `automated`) et son horodatage :
 * une présence sans méthode vérifiable n'est pas une preuve, et la contrainte
 * CHECK exige une `evidence` pour tout check manuel.
 *
 * @property int $id
 * @property int $geo_query_id
 * @property string $engine
 * @property bool $is_present
 * @property int|null $position
 * @property string $method
 * @property string|null $evidence
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
        'is_present',
        'position',
        'method',
        'evidence',
        'observed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
            'position' => 'integer',
            'observed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GeoQuery, $this> */
    public function query(): BelongsTo
    {
        return $this->belongsTo(GeoQuery::class, 'geo_query_id');
    }

    /**
     * Un check manuel sans preuve est refusé AVANT la base, avec un message
     * exploitable — la contrainte CHECK restant le dernier rempart.
     */
    public function isVerifiable(): bool
    {
        return $this->method !== 'manual' || ($this->evidence !== null && trim($this->evidence) !== '');
    }

    /** @param  Builder<self>  $query */
    public function scopePresent(Builder $query): void
    {
        $query->where('is_present', true);
    }
}
