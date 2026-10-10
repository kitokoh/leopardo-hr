<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Geo;

use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;

/**
 * GEO-03 (#8352, BC-33 GEO) — façade transverse du core géospatial.
 *
 * SEUL point d'entrée des verticales (VTC, Pharmacy, Delivery…) vers les
 * calculs de positionnement : aucune verticale n'importe les implémentations
 * du module Geo (garde d'isolation #5584), elle résout ce contrat partagé
 * depuis le conteneur — l'implémentation est fournie par le module Geo et
 * bindée dans GeoServiceProvider.
 *
 * GEO-04 (#8353) étendra ce contrat à la recherche des plus proches.
 */
interface GeoServiceContract
{
    /** Distance géodésique « à vol d'oiseau » en mètres (entier arrondi). */
    public function distanceMeters(GeoPoint $from, GeoPoint $to): int;

    /** Distance géodésique « à vol d'oiseau » (value object). */
    public function distance(GeoPoint $from, GeoPoint $to): Distance;

    /** Moteur actif (`postgis` | `haversine`) — diagnostic et tests. */
    public function distanceEngine(): string;
}
