<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\NearestSearchInterface;
use App\Shared\Contracts\Geo\GeoNativeLocatable;
use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;
use App\Shared\Geo\NearestResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * GEO-04 (#8353, BC-34 GEO) — recherche « le plus proche » sur modèles
 * Eloquent enregistrés (GeoLocatable).
 *
 * Stratégie SQL selon la capacité détectée (décision D1) :
 *  - PostGIS : ST_DWithin (geography) + tri KNN `<->` (spec §4.3) ;
 *  - repli Haversine : pré-filtre bounding-box indexé + formule exacte.
 *
 * Garanties : bindings paramétrés partout (aucune interpolation de
 * coordonnées), identifiants de colonnes validés par allowlist regex, une
 * seule requête (aucun N+1), scope tenant du modèle (BelongsToCompany)
 * appliqué automatiquement — `company_id` toujours en tête de filtre.
 */
final class EloquentNearestSearch implements NearestSearchInterface
{
    /** Rayon moyen de la Terre en mètres (IUGG) — cohérent avec le calculateur. */
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function __construct(
        private readonly SearchableRegistry $registry,
        private readonly GeoCapabilities $capabilities,
    ) {}

    public function nearest(string $type, GeoPoint $center, ?float $radiusKm = null, ?int $limit = null): array
    {
        $modelClass = $this->registry->resolve($type);
        $radiusMeters = $this->resolveRadiusMeters($radiusKm);
        $resolvedLimit = $this->resolveLimit($limit);

        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        // `selectRaw(distance_m)` ci-dessous AJOUTE sa colonne à la liste : sans
        // sélection explicite préalable, la requête ne remonterait QUE la
        // distance (modèle hydraté sans id/name/lat/lng → geoLabel()/geoPoint()
        // en TypeError). Toutes les colonnes métier + la distance calculée.
        $query->select('*');

        $latitudeColumn = $modelClass::geoLatitudeColumn();
        $longitudeColumn = $modelClass::geoLongitudeColumn();
        $lat = $this->wrapColumn($query, $latitudeColumn);
        $lng = $this->wrapColumn($query, $longitudeColumn);

        $query->whereNotNull($latitudeColumn)->whereNotNull($longitudeColumn);

        if ($this->capabilities->postgisAvailable()) {
            // #8467 (déviation D2) : si la table porte une colonne
            // `geography(Point,4326)` GÉNÉRÉE et indexée en GIST, on l'utilise
            // telle quelle. C'est la seule façon dont `ST_DWithin` peut se
            // servir de l'index — sur une expression calculée à la volée,
            // PostgreSQL évalue la fonction sur CHAQUE ligne de la table.
            $nativeColumn = $this->resolveNativeColumn($modelClass);

            if ($nativeColumn !== null) {
                $this->applyPostgisNative($query, $nativeColumn, $center, $radiusMeters);
            } else {
                $this->applyPostgis($query, $lat, $lng, $center, $radiusMeters);
            }
        } else {
            $this->applyHaversine($query, $lat, $lng, $center, $radiusMeters);
        }

        $results = [];

        foreach ($query->limit($resolvedLimit)->get() as $model) {
            /** @var Model&\App\Shared\Contracts\Geo\GeoLocatable $model */
            $distanceMeters = (float) ($model->getAttribute('distance_m') ?? 0.0);
            $results[] = new NearestResult($model, Distance::fromFloatMeters($distanceMeters));
        }

        return $results;
    }

    /**
     * PostGIS : filtre ST_DWithin + distance ST_Distance + tri KNN `<->`
     * (exploite l'index GIST lorsque la colonne est native geography).
     *
     * @param  Builder<Model>  $query
     */
    private function applyPostgis(Builder $query, string $lat, string $lng, GeoPoint $center, float $radiusMeters): void
    {
        // `public.` OBLIGATOIRE : la recherche tourne dans le schéma tenant,
        // PostGIS est installé dans `public` — voir migration #8467.
        $point = 'public.ST_SetSRID(public.ST_MakePoint(?, ?), 4326)::public.geography';
        $column = "public.ST_SetSRID(public.ST_MakePoint({$lng}, {$lat}), 4326)::public.geography";

        $query->whereRaw(
            "public.ST_DWithin({$column}, {$point}, ?)",
            [$center->longitude, $center->latitude, $radiusMeters]
        );

        $query->selectRaw(
            "public.ST_Distance({$column}, {$point}) AS distance_m",
            [$center->longitude, $center->latitude]
        );

        $query->orderByRaw(
            "{$column} <-> {$point}",
            [$center->longitude, $center->latitude]
        );
    }

    /**
     * Requête native sur colonne `geography` générée + index GIST (#8467).
     *
     * Différences décisives avec `applyPostgis()` :
     *  - **`ST_DWithin` sur une COLONNE**, pas sur `ST_SetSRID(ST_MakePoint(…))`.
     *    C'est ce qui permet au planificateur de choisir l'index GIST ; sur
     *    une expression il n'a d'autre choix que de calculer la fonction pour
     *    chaque ligne avant de filtrer.
     *  - **Tri KNN `colonne <-> point`** : avec un index GIST, PostgreSQL
     *    remonte les lignes déjà ordonnées par distance et s'arrête au
     *    `LIMIT` — il ne trie pas tout le jeu de résultats.
     *
     * On garde `ST_Distance` en SELECT : le tri KNN garantit l'ORDRE, pas
     * l'exposition de la valeur, et l'appelant a besoin de `distance_m` pour
     * peupler `NearestResult`.
     *
     * @param  Builder<Model>  $query
     */
    private function applyPostgisNative(Builder $query, string $column, GeoPoint $center, float $radiusMeters): void
    {
        $native = $this->wrapColumn($query, $column);
        $point = 'public.ST_SetSRID(public.ST_MakePoint(?, ?), 4326)::public.geography';

        $query->whereRaw("public.ST_DWithin({$native}, {$point}, ?)", [
            $center->longitude,
            $center->latitude,
            $radiusMeters,
        ]);

        $query->selectRaw("public.ST_Distance({$native}, {$point}) AS distance_m", [
            $center->longitude,
            $center->latitude,
        ]);

        $query->orderByRaw("{$native} <-> {$point}", [
            $center->longitude,
            $center->latitude,
        ]);
    }

    /**
     * Nom de la colonne native si le modèle l'expose ET si elle existe
     * réellement en base.
     *
     * La double vérification n'est pas de la paranoïa : le contrat
     * `GeoNativeLocatable` est déclaré dans le code, la colonne dans une
     * migration. Entre les deux il y a un déploiement — une application
     * déployée AVANT sa migration renverrait un nom de colonne inexistante, et
     * la requête échouerait en SQL au lieu de dégrader vers Haversine. On
     * interroge donc le schéma, avec un cache par modèle pour ne pas le faire
     * à chaque requête.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function resolveNativeColumn(string $modelClass): ?string
    {
        if (! is_subclass_of($modelClass, GeoNativeLocatable::class)) {
            return null;
        }

        $column = $modelClass::geoNativeColumn();

        if (! is_string($column) || $column === '') {
            return null;
        }

        /** @var array<string, bool> $cache */
        static $cache = [];
        $key = $modelClass.'::'.$column;

        if (! array_key_exists($key, $cache)) {
            try {
                $cache[$key] = Schema::hasColumn((new $modelClass)->getTable(), $column);
            } catch (\Throwable) {
                // Schéma illisible (connexion absente, table pas encore
                // créée) : on dégrade, on ne casse pas la recherche.
                $cache[$key] = false;
            }
        }

        return $cache[$key] ? $column : null;
    }

    /**
     * Repli Haversine : pré-filtre bounding-box (index btree lat/lng) puis
     * formule exacte en WHERE et en SELECT — même forme que la spec §4.3.
     *
     * @param  Builder<Model>  $query
     */
    private function applyHaversine(Builder $query, string $lat, string $lng, GeoPoint $center, float $radiusMeters): void
    {
        $latDelta = $radiusMeters / self::EARTH_RADIUS_METERS * (180.0 / M_PI);
        $cosLatitude = max(0.01, abs(cos(deg2rad($center->latitude))));
        $lngDelta = $radiusMeters / (self::EARTH_RADIUS_METERS * $cosLatitude) * (180.0 / M_PI);

        $query->whereBetween($this->rawIdentifier($lat), [$center->latitude - $latDelta, $center->latitude + $latDelta]);
        $query->whereBetween($this->rawIdentifier($lng), [$center->longitude - $lngDelta, $center->longitude + $lngDelta]);

        $haversine = '(2 * '.self::EARTH_RADIUS_METERS." * ASIN(SQRT(POWER(SIN(RADIANS(? - {$lat}) / 2), 2)"
            ." + COS(RADIANS(?)) * COS(RADIANS({$lat})) * POWER(SIN(RADIANS(? - {$lng}) / 2), 2))))";

        $query->whereRaw(
            "{$haversine} <= ?",
            [$center->latitude, $center->latitude, $center->longitude, $radiusMeters]
        );

        $query->selectRaw(
            "{$haversine} AS distance_m",
            [$center->latitude, $center->latitude, $center->longitude]
        );

        $query->orderBy('distance_m');
    }

    private function resolveRadiusMeters(?float $radiusKm): float
    {
        $defaultKm = (float) config('geo.nearest.default_radius_km', 10.0);
        $maxKm = (float) config('geo.nearest.max_radius_km', 50.0);

        $km = $radiusKm ?? $defaultKm;
        $km = max(0.001, min($maxKm, $km));

        return $km * 1000.0;
    }

    private function resolveLimit(?int $limit): int
    {
        $configured = (int) config('geo.nearest.limit', 20);
        $resolved = $limit ?? $configured;

        return max(1, min(100, $resolved));
    }

    /**
     * Qualifie et quote un identifiant de colonne (segments validés par
     * allowlist regex — aucune injection d'identifiant possible).
     *
     * @param  Builder<Model>  $query
     */
    private function wrapColumn(Builder $query, string $column): string
    {
        $qualified = str_contains($column, '.')
            ? $column
            : $query->getModel()->getTable().'.'.$column;

        $parts = explode('.', $qualified);

        foreach ($parts as $part) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
                throw new InvalidArgumentException("Identifiant de colonne géographique invalide : « {$column} ».");
            }
        }

        return implode('.', array_map(static fn (string $part): string => '"'.$part.'"', $parts));
    }

    /** Re-lit le nom de colonne non quoté pour whereBetween (Eloquent quote). */
    private function rawIdentifier(string $wrapped): string
    {
        return str_replace('"', '', $wrapped);
    }
}
