<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;

/**
 * REST-OS-8 (#8610) — Reçu client imprimable.
 *
 * Objet immuable et PUR (aucune dépendance Eloquent/infra), comme
 * {@see KitchenTicket} : le renderer ne connaît que ce contrat, jamais les
 * modèles. Cela permet de tester le rendu ESC/POS sans base de données.
 *
 * Différence structurante avec le ticket cuisine : un reçu client porte des
 * MONTANTS et des MENTIONS LÉGALES. Les montants sont en minor units entières
 * (aucun flottant) et proviennent du calcul serveur — jamais du client.
 *
 * `mentions` est une liste ORDONNÉE de lignes de mentions légales, déjà
 * résolues par juridiction (voir {@see \App\Modules\RestaurantManager\Application\Services\ReceiptMentionsResolver}).
 * Le renderer les imprime telles quelles : il ne connaît aucune règle fiscale.
 */
final readonly class CustomerReceipt
{
    /**
     * @param  list<CustomerReceiptLine>  $lines
     * @param  list<string>  $mentions  Mentions légales, une par ligne.
     */
    public function __construct(
        public PrintTicketKind $kind,
        public int $orderId,
        public string $reference,
        public int $branchId,
        public string $branchName,
        public ?string $branchAddress,
        public ?string $branchPhone,
        public OrderType $orderType,
        public ?string $tableLabel,
        public ?int $covers,
        public array $lines,
        public int $subtotalMinor,
        public int $taxMinor,
        public int $discountMinor,
        public int $totalMinor,
        public int $paidMinor,
        public int $changeMinor,
        public string $currency,
        public int $revision,
        public string $printedAt,
        public array $mentions = [],
    ) {}

    /**
     * Indique si la commande est intégralement réglée.
     *
     * Un reçu ne doit JAMAIS confirmer un paiement non validé (#8610) : le
     * renderer s'appuie sur ce drapeau pour imprimer « NON ACQUITTÉ » au lieu
     * d'un remerciement de fin de vente.
     */
    public function isFullyPaid(): bool
    {
        return $this->paidMinor >= $this->totalMinor;
    }
}
