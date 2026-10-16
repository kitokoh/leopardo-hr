<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\ValueObjects\KitchenTicket;

/**
 * Port de rendu d'un ticket imprimable (REST-OS-1, Mission Leopardo
 * Restaurant OS).
 *
 * L'implémentation produit un flux d'octets prêt à être envoyé à une
 * imprimante thermique (ESC/POS). Le rendu est PUR : aucun accès réseau, base
 * ou matériel — l'impression réelle est la responsabilité du bridge local
 * (REST-OS-3), qui consomme ces octets.
 */
interface OrderTicketRendererInterface
{
    /**
     * Rend le ticket en un flux ESC/POS (octets binaires).
     *
     * @param  int  $paperWidth  Largeur papier en millimètres (58 ou 80).
     */
    public function render(KitchenTicket $ticket, int $paperWidth = 80): string;
}
