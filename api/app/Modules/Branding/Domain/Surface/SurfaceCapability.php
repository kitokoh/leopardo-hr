<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Surface;

/**
 * Capacités publiques qu'une surface peut offrir (BC-41, #9579).
 *
 * ## Registre fermé
 *
 * Une capacité est un PARCOURS que le front sait rendre. En ajouter une sans
 * que le front la connaisse produirait un bouton mort — c'est exactement ce
 * que `PublicPagePresenter` (BC PAGES) évite en calculant les actions à partir
 * de l'état réel plutôt qu'en les stockant.
 *
 * ## Ce qu'une capacité n'est PAS
 *
 * Ce n'est pas une permission ni un entitlement : c'est une déclaration de
 * forme (« cette verticale sait prendre des commandes »). Le fait qu'un tenant
 * donné puisse réellement commander dépend de son état (PSP actif, module
 * installé) et reste calculé au rendu, pas déclaré ici.
 */
enum SurfaceCapability: string
{
    /** Commander en ligne (restaurant, retail, pharmacie). */
    case Order = 'order';

    /** Réserver une table ou une chambre. */
    case Reserve = 'reserve';

    /** Prendre rendez-vous (santé, services). */
    case Book = 'book';

    /** Demander un devis (B2B, trade). */
    case Quote = 'quote';

    /** Présenter une ordonnance (pharmacie). */
    case Prescription = 'prescription';

    /** Réserver un trajet (VTC, travel). */
    case Ride = 'ride';

    /** Écrire à l'établissement. */
    case Contact = 'contact';

    /** Obtenir l'itinéraire. */
    case Directions = 'directions';

    /** Consulter les horaires / l'ouverture. */
    case Hours = 'hours';

    /** Acheter un billet (billetterie). */
    case Ticket = 'ticket';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Commander',
            self::Reserve => 'Réserver',
            self::Book => 'Prendre rendez-vous',
            self::Quote => 'Demander un devis',
            self::Prescription => 'Ordonnance',
            self::Ride => 'Réserver un trajet',
            self::Contact => 'Contact',
            self::Directions => 'Itinéraire',
            self::Hours => 'Horaires',
            self::Ticket => 'Billets',
        };
    }
}
