<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface\Profiles;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Surface\SurfaceCapability;
use App\Modules\Branding\Domain\Surface\SurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileProvider;

/**
 * Profil de surface de la verticale restauration (BC-41, #9579).
 *
 * ## Ce qui rend cette surface « spécialisée »
 *
 * Un restaurant public ne se contente pas d'un hero et d'un formulaire de
 * contact : le visiteur veut voir le menu, commander ou réserver, et savoir
 * quand c'est ouvert. Le profil déclare ces parcours et l'ordre des blocs qui
 * les portent.
 *
 * ## Pourquoi `order` ET `reserve`
 *
 * Les deux existent dans la verticale : un restaurant à emporter n'a pas de
 * réservation, un gastronomique n'a pas de commande en ligne. Le profil
 * déclare les deux capacités ; c'est le rendu qui n'affiche que celles que
 * l'établissement peut réellement honorer (PSP actif, tables configurées).
 * Déclarer une capacité n'est pas promettre un bouton.
 */
final class RestaurantSurfaceProfile implements SurfaceProfileProvider
{
    public function vertical(): string
    {
        return BrandVertical::Restaurant->value;
    }

    public function profile(): SurfaceProfile
    {
        return new SurfaceProfile(
            vertical: $this->vertical(),
            label: 'Restauration',
            capabilities: [
                SurfaceCapability::Order->value,
                SurfaceCapability::Reserve->value,
                SurfaceCapability::Hours->value,
                SurfaceCapability::Directions->value,
                SurfaceCapability::Contact->value,
            ],
            blockTypes: [
                'core.hero',
                'restaurant.menu',
                'core.gallery',
                'core.testimonials',
                'core.contact',
                'core.footer',
            ],
            theme: [
                'density' => 'comfortable',
                'radius' => '0.75rem',
                'font_family' => 'system-ui, sans-serif',
                'image_ratio' => '4:3',
            ],
            primaryCta: SurfaceCapability::Order->value,
        );
    }
}
