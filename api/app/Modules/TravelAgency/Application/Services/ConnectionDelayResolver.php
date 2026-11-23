<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Services;

use App\Modules\TravelAgency\Domain\Models\TravelStation;

/**
 * M5 — Résolution du délai minimal de correspondance applicable à un échange.
 *
 * Le délai n'est plus une constante : il se résout en cascade, du plus
 * spécifique au plus général.
 *
 * 1. **Surcharge explicite de l'appelant** (`min_connection_minutes` dans la
 *    requête) — un exploitant peut vouloir une recherche plus permissive pour
 *    un cas ponctuel. Bornée à [15, 240] par la validation de la requête.
 * 2. **Réglage de la gare de correspondance** (`travel_stations.min_connection_minutes`)
 *    — c'est le cas d'usage de M5 : chaque gare impose son propre délai.
 * 3. **Défaut du tenant** (`config('travel.connections.default_min_minutes')`).
 *
 * La gare de correspondance est celle de la VILLE INTERMÉDIAIRE : c'est là que
 * le voyageur change de véhicule, donc là que le délai s'applique. Si la ville
 * compte plusieurs gares, on retient la plus contraignante (le maximum) : une
 * correspondance n'est valide que si elle est tenable dans TOUTES les gares
 * concernées — retenir le minimum laisserait passer un itinéraire infaisable
 * depuis la gare la plus exigeante.
 */
final class ConnectionDelayResolver
{
    /** Bornes du domaine, alignées sur la contrainte CHECK de la migration. */
    public const MIN_ALLOWED = 15;

    public const MAX_ALLOWED = 240;

    /**
     * Délai applicable pour une correspondance dans `$hubCityId`.
     *
     * @param  int|null  $requested  Surcharge explicite de l'appelant (null = aucune).
     */
    public function resolve(?int $hubCityId, ?int $requested = null): int
    {
        if ($requested !== null) {
            return $this->clamp($requested);
        }

        if ($hubCityId !== null) {
            $stationDelay = $this->stationDelay($hubCityId);

            if ($stationDelay !== null) {
                return $stationDelay;
            }
        }

        return $this->clamp((int) config('travel.connections.default_min_minutes', 45));
    }

    /**
     * Délai le plus contraignant imposé par les gares actives d'une ville.
     *
     * `null` quand aucune gare de la ville ne porte de surcharge : l'appelant
     * retombe alors sur le défaut du tenant.
     */
    public function stationDelay(int $cityId): ?int
    {
        $delays = TravelStation::query()
            ->where('city_id', $cityId)
            ->whereNotNull('min_connection_minutes')
            ->pluck('min_connection_minutes')
            ->map(fn ($value): int => (int) $value)
            ->all();

        if ($delays === []) {
            return null;
        }

        return $this->clamp(max($delays));
    }

    private function clamp(int $minutes): int
    {
        return max(self::MIN_ALLOWED, min(self::MAX_ALLOWED, $minutes));
    }
}
