<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Fleet;

use Illuminate\Support\Carbon;

/**
 * Synchroniseur partagé des données de tracking de flotte (BC-26 FLEET).
 *
 * Permet aux modules consommateurs (Attendance — endpoints de
 * synchronisation manuelle `TrackingSyncController`) de déclencher les
 * synchronisations SANS import croisé `Modules/X -> Modules/Fleet`
 * (règle d'isolation #5584, chantier BOS-023 #8211, cycle Fleet↔Attendance
 * #8298) — ils ne dépendent que de ce contrat, implémenté par
 * `Fleet\Infrastructure\Services\FleetTrackingSyncService`.
 *
 * Les signatures reprennent à l'identique les méthodes publiques du service
 * historique (#7401) — comportement strictement préservé (clés de retour,
 * isolation tenant via `$companyId`, fenêtre maximale).
 */
interface FleetTrackingSynchronizer
{
    /**
     * Fenêtre maximale d'une synchronisation de trajets, en jours (#3369).
     */
    public const MAX_WINDOW_DAYS = 90;

    /**
     * Appareils remontés par la plateforme de tracking.
     *
     * @return array<int|string, mixed>
     */
    public function devices(): array;

    /**
     * Lie les appareils de la plateforme aux véhicules du tenant.
     *
     * @param  array<int|string, mixed>  $devices
     * @return array{devices:int, linked:int}
     */
    public function syncDevices(string $companyId, array $devices): array;

    /**
     * Persiste la dernière position connue de chaque véhicule tracké.
     *
     * @return array{written:int, vehicles:int, vehicles_with_position:int}
     */
    public function syncLatestPositions(string $companyId, ?int $vehicleLimit = null): array;

    /**
     * Persiste les trajets des véhicules sur une fenêtre bornée.
     *
     * @return array{written:int, vehicles:int}
     */
    public function syncTrips(string $companyId, Carbon $from, Carbon $to, ?int $vehicleLimit = null): array;
}
