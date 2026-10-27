<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Contracts;

use App\Modules\RestaurantManager\Domain\ValueObjects\CustomerReceipt;

/**
 * Port de rendu d'un REÇU CLIENT (REST-OS-8, #8610).
 *
 * Contrat distinct de {@see OrderTicketRendererInterface} (ticket cuisine) :
 * un reçu porte des montants, une devise et des mentions légales, un ticket
 * cuisine porte des instructions de préparation. Les mélanger dans une seule
 * signature obligerait chaque implémentation à ignorer la moitié de son
 * argument — c'est exactement le défaut corrigé ici (le `kind` était stocké
 * mais ignoré au rendu, donc un reçu client imprimait un ticket cuisine).
 *
 * Le rendu est PUR : aucun accès réseau, base ou matériel. L'impression réelle
 * reste la responsabilité du bridge local (REST-OS-3).
 */
interface CustomerReceiptRendererInterface
{
    /**
     * Rend le reçu en un flux ESC/POS (octets binaires).
     *
     * @param  int  $paperWidth  Largeur papier en millimètres (58 ou 80).
     */
    public function render(CustomerReceipt $receipt, int $paperWidth = 80): string;
}
