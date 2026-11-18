<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale pharmacie (BC-41, #9579).
 *
 * ## Ce qui distingue cette surface
 *
 * Une officine publique ne vend pas comme un commerce : le parcours
 * « ordonnance » (dépôt, renouvellement) est premier, et la vente en ligne de
 * médicaments est encadrée. Le profil déclare `prescription` en capacité
 * primaire — c'est le geste que le visiteur vient faire.
 *
 * ## Données de santé
 *
 * Aucune donnée patient n'est exposée sur cette surface : le profil ne décrit
 * que des parcours publics. Les PII de santé restent dans le module Pharmacy,
 * isolées par tenant, avec leurs permissions et leur rétention propres.
 */
final class PharmacySurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Pharmacy->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Pharmacie',
            capabilities: [
                SurfaceCapability::Prescription->value,
                SurfaceCapability::Order->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'pharmacy.services',
                'core.features',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'comfortable',
                'radius' => '0.5rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '16:9',
            ],
            primaryCta: SurfaceCapability::Prescription->value,
        );
    }
}
