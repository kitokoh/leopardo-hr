<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale hôtellerie (BC-41, #9579).
 *
 * Le parcours est la réservation d'un séjour avec dates : le visiteur compare
 * des chambres et réserve. Densité aérée, images larges — on vend un lieu.
 */
final class HospitalitySurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Hospitality->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Hôtellerie',
            capabilities: [
                SurfaceCapability::Reserve->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'hospitality.rooms',
                'core.gallery',
                'core.testimonials',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'spacious',
                'radius' => '0.75rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '3:2',
            ],
            primaryCta: SurfaceCapability::Reserve->value,
        );
    }
}
