<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale station-service (BC-41, #9579).
 *
 * Une station publique sert deux besoins très concrets : savoir si elle est
 * ouverte et où elle est. Le parcours de vente au comptoir n'est pas un
 * parcours web — il reste dans le module FuelStation. La surface est donc
 * courte et factuelle.
 */
final class FuelSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Fuel->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Station-service',
            capabilities: [
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'fuel.stations',
                'core.features',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'compact',
                'radius' => '0.25rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '16:9',
            ],
            primaryCta: SurfaceCapability::Directions->value,
        );
    }
}
