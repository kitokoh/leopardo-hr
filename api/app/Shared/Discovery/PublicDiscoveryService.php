<?php

declare(strict_types=1);

namespace App\Shared\Discovery;

use App\Shared\Contracts\Discovery\PublicDiscoverableProvider;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SUPERAPP-P0 — Agrégateur de découverte publique cross-verticale.
 *
 * Collecte tous les providers tagués `public.discoverable`, fusionne leurs
 * items publics, déduplique (type + slug), calcule les distances via le core
 * géospatial (`GeoServiceContract`, jamais de haversine ré-implémentée), trie
 * et pagine. Aucune table, aucune écriture : composition de lecture pure.
 *
 * Invariants :
 *  - un `?type=` inconnu est REFUSÉ (422) — jamais de filtré silencieux ;
 *  - `?near=` fourni → seules les entités géolocalisées sont retenues, dans le
 *    rayon, triées par distance croissante ;
 *  - `per_page` borné (≤ 50) ; les items exposés ne portent aucune donnée
 *    interne (voir {@see PublicDiscoverableProvider}).
 */
final class PublicDiscoveryService
{
    public const MAX_PER_PAGE = 50;

    public const DEFAULT_PER_PAGE = 20;

    public const DEFAULT_RADIUS_KM = 10.0;

    public const MAX_RADIUS_KM = 50.0;

    /**
     * @param  iterable<PublicDiscoverableProvider>  $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly GeoServiceContract $geo,
    ) {}

    /**
     * Types canoniques exposés, triés — sert de valeurs autorisées à `?type=`.
     *
     * @return list<string>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->providers as $provider) {
            $types[$provider->type()] = true;
        }

        $types = array_keys($types);
        sort($types);

        return $types;
    }

    /**
     * Découverte agrégée.
     *
     * @param  array{q?:string, city?:string, type?:string, near?:string, radius_km?:float|int|string, per_page?:int|string, page?:int|string}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws HttpException 422 si `type` est inconnu
     */
    public function discover(array $filters): array
    {
        $requestedType = isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== ''
            ? $filters['type']
            : null;

        $types = $this->types();

        if ($requestedType !== null && ! in_array($requestedType, $types, true)) {
            abort(422, 'Unknown discovery type.');
        }

        $perPage = $this->boundedInt($filters['per_page'] ?? null, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = max(1, $this->boundedInt($filters['page'] ?? null, 1, PHP_INT_MAX));

        $near = isset($filters['near']) && is_string($filters['near']) && $filters['near'] !== ''
            ? $filters['near']
            : null;

        $center = $near !== null ? $this->parseNear($near) : null;
        $radiusKm = $this->resolveRadius($filters['radius_km'] ?? null);

        /** @var array<string, array<string, mixed>> $items indexé par "type|slug" (déduplication) */
        $items = [];

        foreach ($this->providers as $provider) {
            if ($requestedType !== null && $provider->type() !== $requestedType) {
                continue;
            }

            foreach ($provider->search([
                'q' => $filters['q'] ?? null,
                'city' => $filters['city'] ?? null,
                // Borne large par provider : la pagination finale est appliquée ici.
                'limit' => self::MAX_PER_PAGE * 10,
            ]) as $item) {
                $key = ($item['type'] ?? $provider->type()).'|'.($item['slug'] ?? '');

                if ($key === '|' || isset($items[$key])) {
                    continue;
                }

                $items[$key] = $this->withDistance($item, $center);
            }
        }

        $list = array_values(array_filter(
            $items,
            static fn (array $item): bool => ! ($item['__excluded'] ?? false)
        ));

        if ($center !== null) {
            $list = array_values(array_filter(
                $list,
                static fn (array $item): bool => isset($item['distance_km']) && $item['distance_km'] <= $radiusKm
            ));

            usort($list, static function (array $a, array $b): int {
                return ($a['distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['distance_km'] ?? PHP_FLOAT_MAX);
            });
        } else {
            usort($list, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
        }

        $list = array_map(static function (array $item): array {
            unset($item['__excluded']);

            return $item;
        }, $list);

        $total = count($list);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($list, $offset, $perPage);

        return [
            'data' => array_values($paged),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
                'last_page' => $perPage > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1,
                'types' => $types,
                'radius_km' => $center !== null ? $radiusKm : null,
            ],
        ];
    }

    /**
     * Ajoute `distance_km` si le point de recherche ET l'entité sont
     * géolocalisés. Marque `__excluded` lorsqu'un point de recherche est fourni
     * mais que l'entité ne l'est pas (elle ne peut pas satisfaire la proximité).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withDistance(array $item, ?GeoPoint $center): array
    {
        if ($center === null) {
            return $item;
        }

        $latitude = $item['latitude'] ?? null;
        $longitude = $item['longitude'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            $item['__excluded'] = true;

            return $item;
        }

        $distance = $this->geo->distance($center, new GeoPoint((float) $latitude, (float) $longitude));

        $item['distance_km'] = round($distance->meters / 1000.0, 2);

        return $item;
    }

    /**
     * `near=lat,lng` → point valide (422 hors domaine).
     */
    private function parseNear(string $near): GeoPoint
    {
        $parts = explode(',', $near, 2);

        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            abort(422, 'Invalid near parameter.');
        }

        try {
            return new GeoPoint((float) $parts[0], (float) $parts[1]);
        } catch (\Throwable) {
            abort(422, 'Invalid near parameter.');
        }
    }

    private function resolveRadius(float|int|string|null $radius): float
    {
        if (! is_numeric($radius)) {
            return self::DEFAULT_RADIUS_KM;
        }

        $value = (float) $radius;

        if ($value < 0.1 || $value > self::MAX_RADIUS_KM) {
            abort(422, 'Invalid radius_km.');
        }

        return $value;
    }

    private function boundedInt(int|string|null $value, int $default, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $value = (int) $value;

        if ($value < 1) {
            return $default;
        }

        return min($value, $max);
    }
}
