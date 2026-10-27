<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

/**
 * Ligne d'un reçu client (REST-OS-8, #8610).
 *
 * Objet immuable et pur. Contrairement à {@see KitchenTicketLine}, une ligne de
 * reçu porte un PRIX : le client doit pouvoir vérifier ce qu'il paie.
 *
 * `unitPriceMinor` et `lineTotalMinor` sont en minor units entières — le
 * renderer ne fait aucune arithmétique monétaire, il ne fait que formater.
 */
final readonly class CustomerReceiptLine
{
    public function __construct(
        public string $name,
        public string $quantity,
        public int $unitPriceMinor,
        public int $lineTotalMinor,
    ) {}
}
