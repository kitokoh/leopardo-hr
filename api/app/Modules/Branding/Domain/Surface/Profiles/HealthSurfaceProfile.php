<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale santé (BC-41, #9579).
 *
 * Le parcours est la prise de rendez-vous. Aucune donnée patient n'est exposée
 * ici : le profil ne décrit que des parcours publics, les PII de santé restent
 * dans le module HealthManager avec leurs permissions et leur rétention.
 */
final class HealthSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Health->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Santé',
            capabilities: [
                SurfaceCapability::Book->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'health.services',
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
            primaryCta: SurfaceCapability::Book->value,
        );
    }
}
