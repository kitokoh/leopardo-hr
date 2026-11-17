<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale VTC / mobilité (BC-41, #9579).
 *
 * Le parcours est le trajet : le visiteur veut réserver une course, pas
 * parcourir un catalogue. La surface est volontairement minimale — un hero,
 * le formulaire de course, le contact. Tout le reste distrait d'un geste qui
 * doit prendre dix secondes.
 */
final class VtcSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Vtc->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'VTC / mobilité',
            capabilities: [
                SurfaceCapability::Ride->value,
                SurfaceCapability::Quote->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'vtc.booking',
                'core.features',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'compact',
                'radius' => '0.75rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '16:9',
            ],
            primaryCta: SurfaceCapability::Ride->value,
        );
    }
}
