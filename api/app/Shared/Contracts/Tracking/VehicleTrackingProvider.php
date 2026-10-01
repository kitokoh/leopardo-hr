<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Tracking;

use Illuminate\Support\Carbon;

/**
 * Fournisseur partagé de données de tracking véhicules (BC-12 ATTENDANCE /
 * intégration Traccar).
 *
 * Permet aux modules consommateurs (Fleet — carte temps réel, position d'un
 * véhicule, synchronisations planifiées…) de requêter la plateforme GPS
 * SANS import croisé `Modules/X -> Modules/Attendance` (règle d'isolation
 * #5584, chantier BOS-023 #8211, cycle Fleet↔Attendance #8298) — ils ne
 * dépendent que de ce contrat, implémenté par
 * `Attendance\Infrastructure\Services\TraccarService`.
 *
 * Les signatures reprennent à l'identique les méthodes de `TraccarService`
 * effectivement consommées par Fleet — comportement strictement préservé
 * (tableaux bruts de l'API Traccar, fail-open quand non configuré).
 */
interface VehicleTrackingProvider
{
    /**
     * Appareils Traccar connus de la plateforme.
     *
     * @return array<int|string, mixed>
     */
    public function getDevices(): array;

    /**
     * Positions d'un appareil sur une fenêtre optionnelle.
     *
     * @return array<int|string, mixed>
     */
    public function getPositions(int $deviceId, ?Carbon $from = null, ?Carbon $to = null): array;

    /**
     * Dernière position connue d'un appareil, null si aucune.
     *
     * @return array<string, mixed>|null
     */
    public function getLastPosition(int $deviceId): ?array;

    /**
     * Dernière position de plusieurs appareils en UN appel — évite le N+1
     * HTTP (#3148).
     *
     * @param  list<int>  $deviceIds
     * @return array<int, array<string, mixed>|null> deviceId => position|null
     */
    public function getLastPositions(array $deviceIds): array;

    /**
     * Trajets d'un appareil sur une fenêtre obligatoire.
     *
     * @return array<int|string, mixed>
     */
    public function getTrips(int $deviceId, Carbon $from, Carbon $to): array;
}
