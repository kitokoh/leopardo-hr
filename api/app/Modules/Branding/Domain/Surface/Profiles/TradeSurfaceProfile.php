<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale commerce B2B (BC-41, #9579).
 *
 * Le parcours est la demande de devis : un acheteur professionnel ne commande
 * pas en un clic, il demande une offre. Le profil met donc `quote` en avant et
 * n'expose pas `order` — un bouton « Commander » sur une surface B2B promet un
 * parcours qui n'existe pas.
 */
final class TradeSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Trade->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Commerce B2B',
            capabilities: [
                SurfaceCapability::Quote->value,
                SurfaceCapability::Contact->value,
                SurfaceCapability::Hours->value,
            ],
            blockTypes: [
                'core.hero',
                'trade.catalog',
                'core.features',
                'core.testimonials',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'compact',
                'radius' => '0.25rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '4:3',
            ],
            primaryCta: SurfaceCapability::Quote->value,
        );
    }
}
