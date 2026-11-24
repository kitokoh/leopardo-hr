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
 *
 * ## Pourquoi un cache, et pourquoi il est sûr
 *
 * `ConnectionSearchAction` appelle `resolve()` dans une boucle imbriquée
 * (chaque premier tronçon × chaque second tronçon). Sans cache, une recherche
 * dense relit `travel_stations` une fois PAR PAIRE CANDIDATE, alors que la
 * réponse ne dépend que de la ville intermédiaire — quelques dizaines de villes
 * au plus. C'est un N+1 pur : le coût croît avec le nombre de combinaisons
 * d'horaires, pas avec la donnée.
 *
 * Le cache est une **mémoïsation par instance**, et l'instance est enregistrée
 * `scoped` (voir `TravelAgencyServiceProvider`) : Laravel en crée une par
 * requête HTTP et la jette entre deux jobs de queue. La durée de vie du cache
 * est donc exactement celle d'une requête — jamais celle du processus. Un
 * worker de queue qui traiterait mille réservations ne peut pas servir le
 * réglage d'une gare lu une heure plus tôt.
 *
 * Reste le cas « écrire puis relire dans la MÊME requête » : le cache
 * survivrait à la mise à jour. C'est pourquoi `forget()` existe et que le CRUD
 * des gares l'appelle après chaque écriture. Le cache n'est donc pas
 * seulement borné dans le temps, il est aussi explicitement invalidé par le
 * seul chemin d'écriture HTTP.
 */
final class ConnectionDelayResolver
{
    /** Bornes du domaine, alignées sur la contrainte CHECK de la migration. */
    public const MIN_ALLOWED = 15;

    public const MAX_ALLOWED = 240;

    /**
     * Mémoïsation par ville : `cityId => délai|null`.
     *
     * `array_key_exists` distingue « pas encore résolu » de « résolu, aucune
     * surcharge » — sans quoi une ville sans réglage serait relue à chaque
     * appel, ce qui est précisément le cas le plus fréquent.
     *
     * @var array<int, int|null>
     */
    private array $memo = [];

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
        if (array_key_exists($cityId, $this->memo)) {
            return $this->memo[$cityId];
        }

        $delays = TravelStation::query()
            ->where('city_id', $cityId)
            ->whereNotNull('min_connection_minutes')
            ->pluck('min_connection_minutes')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return $this->memo[$cityId] = $delays === [] ? null : $this->clamp(max($delays));
    }

    /**
     * Invalide le cache d'une ville, ou de toutes les villes.
     *
     * À appeler après toute écriture d'une gare : sans cela, une requête qui
     * modifie un réglage puis relit une recherche servirait l'ancienne valeur.
     */
    public function forget(?int $cityId = null): void
    {
        if ($cityId === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[$cityId]);
    }

    private function clamp(int $minutes): int
    {
        return max(self::MIN_ALLOWED, min(self::MAX_ALLOWED, $minutes));
    }
}
