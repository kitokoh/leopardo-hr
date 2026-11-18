<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale commerce de détail (BC-41, #9579).
 *
 * Le parcours dominant est le catalogue : le visiteur veut voir les produits,
 * les prix, et commander. La densité est plus serrée que la restauration —
 * une grille de produits se lit en densité `compact`, pas en `comfortable`.
 */
final class RetailSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Retail->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Commerce de détail',
            capabilities: [
                SurfaceCapability::Order->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'retail.catalog',
                'core.features',
                'core.testimonials',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'compact',
                'radius' => '0.5rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '1:1',
            ],
            primaryCta: SurfaceCapability::Order->value,
        );
    }
}
