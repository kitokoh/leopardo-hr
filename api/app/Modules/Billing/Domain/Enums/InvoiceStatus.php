<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enums;

/**
 * États d'une facture plateforme (DEP-BC21 #6248).
 *
 * Machine à états explicite, alignée sur la contrainte DB
 * `invoices_status_check` (draft|sent|paid|overdue|cancelled — la CHECK
 * rejette tout autre valeur, ex. `pending`) :
 *
 *   draft ──► sent ──► overdue ──► paid
 *     │       │ │       │  ▲
 *     │       │ └───► paid │
 *     │       └────► cancelled (terminal)
 *     └────► cancelled
 *
 * `paid` et `cancelled` sont terminaux. La transition `overdue → paid`
 * correspond à un paiement reçu après la date d'échéance.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';

    case Sent = 'sent';

    case Paid = 'paid';

    case Overdue = 'overdue';

    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Paid, self::Overdue, self::Cancelled],
            self::Overdue => [self::Paid, self::Cancelled],
            self::Paid => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
