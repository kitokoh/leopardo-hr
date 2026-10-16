<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;

/**
 * Ticket imprimable — représentation immuable, indépendante de la persistance
 * (REST-OS-1, Mission Leopardo Restaurant OS).
 *
 * Le rendu (ESC/POS) reçoit ce seul objet : la couche d'impression ne dépend
 * donc ni d'Eloquent ni d'un bridge particulier. `revision` entre dans la clé
 * de déduplication (rejouer la même révision ne réimprime pas).
 */
final readonly class KitchenTicket
{
    /**
     * @param  list<KitchenTicketLine>  $lines
     */
    public function __construct(
        public PrintTicketKind $kind,
        public int $orderId,
        public string $reference,
        public int $branchId,
        public string $branchName,
        public OrderType $orderType,
        public ?string $tableLabel,
        public ?int $covers,
        public array $lines,
        public int $revision,
        public string $printedAt,
        public ?string $note = null,
    ) {
    }
}
