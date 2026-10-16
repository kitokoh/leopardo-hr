<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

/**
 * Ligne d'un ticket cuisine (REST-OS-1, Mission Leopardo Restaurant OS).
 *
 * Objet immuable et pur (aucune dépendance Eloquent/infra) : le renderer ne
 * connaît que ce contrat, jamais les modèles. Cela permet de tester le rendu
 * ESC/POS sans base de données.
 */
final readonly class KitchenTicketLine
{
    public function __construct(
        public string $name,
        public string $quantity,
        public ?string $note = null,
        public bool $isCancelled = false,
    ) {
    }
}
