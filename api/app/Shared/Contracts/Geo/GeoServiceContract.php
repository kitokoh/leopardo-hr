<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Geo;

use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;
use App\Shared\Geo\NearestResult;
use Illuminate\Database\Eloquent\Model;

/**
 * GEO-03 (#8352, BC-33 GEO) — façade transverse du core géospatial.
 *
 * SEUL point d'entrée des verticales (VTC, Pharmacy, Delivery…) vers les
 * calculs de positionnement : aucune verticale n'importe les implémentations
 * du module Geo (garde d'isolation #5584), elle résout ce contrat partagé
 * depuis le conteneur — l'implémentation est fournie par le module Geo et
 * bindée dans GeoServiceProvider.
 *
 * GEO-04 (#8353) : recherche des plus proches via la registry opt-in
 * (`registerSearchable` / `nearest`) — aucune table tenant n'est exposée
 * sans enregistrement explicite (fail-closed).
 */
interface GeoServiceContract
{
    /** Distance géodésique « à vol d'oiseau » en mètres (entier arrondi). */
    public function distanceMeters(GeoPoint $from, GeoPoint $to): int;

    /** Distance géodésique « à vol d'oiseau » (value object). */
    public function distance(GeoPoint $from, GeoPoint $to): Distance;

    /** Moteur actif (`postgis` | `haversine`) — diagnostic et tests. */
    public function distanceEngine(): string;

    /**
     * Enregistre un type recherchable « le plus proche » (appelé par le
     * provider de la verticale — inversion de dépendance, garde #5584).
     *
     * @param  class-string<Model&GeoLocatable>  $modelClass
     */
    public function registerSearchable(string $type, string $modelClass): void;

    /**
     * Entités du type enregistré, dans le rayon, triées par distance
     * croissante (une seule requête — aucun N+1).
     *
     * @return list<NearestResult>
     */
    public function nearest(string $type, GeoPoint $center, ?float $radiusKm = null, ?int $limit = null): array;
}
