<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale billetterie (BC-41, #9579).
 *
 * Le parcours est l'achat d'un billet pour un événement : le visiteur veut
 * voir la programmation et acheter. `ticket` est la capacité primaire, et
 * `order` n'est pas déclarée — un billet n'est pas une commande de produit,
 * le parcours de paiement diffère (places, tarifs, date).
 */
final class TicketingSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Ticketing->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Billetterie',
            capabilities: [
                SurfaceCapability::Ticket->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'ticketing.events',
                'core.gallery',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'comfortable',
                'radius' => '0.75rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '16:9',
            ],
            primaryCta: SurfaceCapability::Ticket->value,
        );
    }
}
