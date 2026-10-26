<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GSI-5 (#8516) — requête stratégique pour la présence dans les moteurs
 * génératifs (GEO).
 *
 * @property int $id
 * @property string $query
 * @property string $locale
 * @property int $priority
 * @property bool $is_active
 *
 * @mixin Builder<static>
 */
class GeoQuery extends Model
{
    protected $table = 'geo_queries';

    protected $fillable = [
        'query',
        'locale',
        'priority',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<GeoCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(GeoCheck::class, 'geo_query_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('priority');
    }
}
