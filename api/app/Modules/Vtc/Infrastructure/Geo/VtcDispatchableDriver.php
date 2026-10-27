<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Geo;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Contracts\Geo\GeoNativeLocatable;
use App\Shared\Geo\GeoPoint;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * « Vue d'adaptation » dispatch des chauffeurs (BC-35 VTC, VTC-04/#8360).
 *
 * Type `vtc_driver` de la registry `geo.searchables` : SEULS les chauffeurs
 * `available` (en service et libres) sont candidats au matching — le scope
 * global le garantit structurellement, le dispatch n'a pas à re-filtrer.
 * Le scope tenant (BelongsToCompany) assure l'isolation : le matching ne
 * propose jamais un chauffeur d'un autre tenant (critère d'acceptation).
 *
 * Les positions sont lues depuis les colonnes décimales WGS 84 du modèle
 * (dérivation v1 documentée en migration 000102) — la géographie est
 * reconstruite à la volée par le core geo BC-34.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property float|null $current_latitude
 * @property float|null $current_longitude
 */
final class VtcDispatchableDriver extends Model implements GeoLocatable, GeoNativeLocatable
{
    use BelongsToCompany;

    protected $table = 'vtc_drivers';

    /**
     * Candidats au dispatch : statut `available` uniquement (spec §5.3).
     */
    protected static function booted(): void
    {
        self::addGlobalScope('dispatchable', function (Builder $query): void {
            $query->where('status', VtcDriverStatus::Available->value);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'current_latitude' => 'float',
            'current_longitude' => 'float',
        ];
    }

    public function geoPoint(): ?GeoPoint
    {
        return GeoPoint::fromNullable($this->current_latitude, $this->current_longitude);
    }

    public function geoLabel(): string
    {
        return $this->name;
    }

    public static function geoLatitudeColumn(): string
    {
        return 'current_latitude';
    }

    public static function geoLongitudeColumn(): string
    {
        return 'current_longitude';
    }

    /**
     * Colonne `geography(Point,4326)` GÉNÉRÉE depuis `current_latitude` /
     * `current_longitude` par la migration #8467, indexée en GIST.
     *
     * La recherche proximale s'en sert quand PostGIS est disponible :
     * `ST_DWithin` peut alors utiliser l'index au lieu d'évaluer une fonction
     * sur chaque ligne. Le service vérifie en plus que la colonne existe
     * réellement en base (cache par modèle), donc renvoyer son nom ici est sûr
     * même si la migration n'a pas encore été jouée.
     *
     * Le type de retour est `string` et non `?string` : PHP autorise la
     * covariance, et cette verticale est MIGRÉE — elle ne renvoie donc jamais
     * `null`. Le contrat, lui, garde `?string` parce que c'est le signal
     * « modèle pas encore migré » utilisé par les autres verticales.
     */
    public static function geoNativeColumn(): string
    {
        return 'current_location';
    }
}
