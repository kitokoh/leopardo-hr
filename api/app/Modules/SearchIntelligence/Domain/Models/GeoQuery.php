<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\GeoCheckMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GSI-5 (#8516) — requête stratégique pour la présence dans les moteurs
 * génératifs (GEO).
 *
 * `reference_pages` liste les pages du parc qui répondent (ou devraient
 * répondre) à cette requête — c'est ce qui relie la veille GEO au travail SEO.
 *
 * @property int $id
 * @property string $query
 * @property string $locale
 * @property string|null $sector
 * @property int $priority
 * @property bool $is_active
 * @property array<int, mixed>|null $reference_pages
 *
 * @mixin Builder<static>
 */
class GeoQuery extends Model
{
    protected $table = 'geo_queries';

    protected $fillable = [
        'query',
        'locale',
        'sector',
        'priority',
        'is_active',
        'reference_pages',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
            'reference_pages' => 'array',
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

    /**
     * Niveau de preuve le PLUS FAIBLE parmi les checks enregistrés sur cette
     * requête. C'est lui qui plafonne ce qu'on peut affirmer publiquement : si
     * tout repose sur un test ponctuel, aucune tendance ne peut être annoncée.
     */
    public function weakestEvidence(): ?GeoCheckMethod
    {
        // `pluck` passe par les casts du modèle : selon la version d'Eloquent
        // on reçoit soit la valeur brute, soit déjà l'enum. On normalise.
        $raw = $this->checks()->pluck('method');

        $methods = $raw->map(static function ($value): GeoCheckMethod {
            if ($value instanceof GeoCheckMethod) {
                return $value;
            }

            return GeoCheckMethod::from((string) $value);
        });

        if ($methods->isEmpty()) {
            return null;
        }

        return $methods->sortBy(static fn (GeoCheckMethod $m): int => $m->strength())->first();
    }
}
