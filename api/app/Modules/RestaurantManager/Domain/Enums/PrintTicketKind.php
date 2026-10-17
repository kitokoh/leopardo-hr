<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Nature d'un ticket imprimé (REST-OS-1, Mission Leopardo Restaurant OS).
 *
 * La nature entre dans l'identité de déduplication d'un travail d'impression
 * (`restaurant_print_jobs` : unique (company, order, ticket_kind, revision)) :
 * un ticket cuisine et un ticket client de la même commande sont deux travaux
 * distincts, mais un même ticket cuisine rejoué n'est jamais imprimé deux fois.
 */
enum PrintTicketKind: string
{
    /** Ticket de préparation envoyé en cuisine (mode impression cuisine). */
    case KITCHEN = 'kitchen';

    /** Bon de commande remis au client (addition / reçu). */
    case CUSTOMER_RECEIPT = 'customer_receipt';

    public function label(): string
    {
        return match ($this) {
            self::KITCHEN => 'Ticket cuisine',
            self::CUSTOMER_RECEIPT => 'Reçu client',
        };
    }

    /**
     * Indique si ce type de ticket est destiné à la file cuisine (mode écran
     * vs mode impression) — seule la catégorie cuisine alimente l'imprimante
     * thermique de cuisine.
     */
    public function isKitchenTicket(): bool
    {
        return $this === self::KITCHEN;
    }
}
