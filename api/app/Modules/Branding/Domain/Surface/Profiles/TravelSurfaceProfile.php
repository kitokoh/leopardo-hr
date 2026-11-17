<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale voyage (BC-41, #9579).
 *
 * Le parcours est la réservation d'un séjour : le visiteur cherche une
 * destination, compare, réserve. La densité est aérée et l'image large — une
 * agence de voyage vend d'abord une envie.
 */
final class TravelSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Travel->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Voyage',
            capabilities: [
                SurfaceCapability::Reserve->value,
                SurfaceCapability::Quote->value,
                SurfaceCapability::Contact->value,
                SurfaceCapability::Directions->value,
            ],
            blockTypes: [
                'core.hero',
                'travel.offers',
                'core.gallery',
                'core.testimonials',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'spacious',
                'radius' => '1rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '16:9',
            ],
            primaryCta: SurfaceCapability::Reserve->value,
        );
    }
}
