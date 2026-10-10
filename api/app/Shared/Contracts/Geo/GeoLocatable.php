<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Geo;

use App\Shared\Geo\GeoPoint;

/**
 * GEO-04 (#8353, BC-33 GEO) — entité exposant une position géographique.
 *
 * Contrat PARTAGÉ (jamais dans le module Geo) : un modèle Eloquent de
 * n'importe quelle verticale (VtcDriver, branche de pharmacie, restaurant…)
 * l'implémente sans importer `App\Modules\Geo\*` (garde d'isolation #5584),
 * puis s'enregistre dans la registry `geo.searchables` — il devient
 * requêtable par « le plus proche » sans code nouveau côté core.
 */
interface GeoLocatable
{
    /** Position courante de l'entité (null si inconnue — exclue du nearest). */
    public function geoPoint(): ?GeoPoint;

    /** Libellé lisible exposé dans les résultats de recherche. */
    public function geoLabel(): string;

    /** Colonne portant la latitude (défaut attendu : `latitude`). */
    public static function geoLatitudeColumn(): string;

    /** Colonne portant la longitude (défaut attendu : `longitude`). */
    public static function geoLongitudeColumn(): string;
}
