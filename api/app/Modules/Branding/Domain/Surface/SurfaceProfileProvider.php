<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface;

/**
 * Contrat qu'une verticale implémente pour fournir SON profil de surface
 * publique (BC-41, #9579).
 *
 * ## Le patron, et pourquoi il est le même que `PageBlockProvider`
 *
 * BC PAGES a déjà résolu ce problème pour les blocs : le module central
 * possède le contenant et le validateur, la verticale possède son schéma et
 * l'enregistre au boot. On reprend exactement le même mécanisme, pour la même
 * raison — sans lui, ajouter une verticale obligerait à modifier le module
 * central, et chaque verticale finirait par importer les autres.
 *
 * ## Ce que le contrat impose
 *
 * `vertical()` DOIT correspondre à un cas de `BrandVertical` (hors `generic`).
 * Le registre le vérifie et refuse un provider qui déclare une verticale
 * inconnue : une Brand de cette verticale n'aurait alors aucun profil, et le
 * repli silencieux masquerait la faute de configuration.
 *
 * `generic` est explicitement interdit : c'est le repli fourni par le socle.
 * Un provider qui le déclarerait écraserait la surface de toutes les Brands
 * sans profil dédié.
 */
interface SurfaceProfileProvider
{
    /**
     * La verticale propriétaire (doit être un cas de `BrandVertical`, hors `generic`).
     */
    public function vertical(): string;

    /**
     * Le profil de surface de cette verticale.
     */
    public function profile(): SurfaceProfile;
}
