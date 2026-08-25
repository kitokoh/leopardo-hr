<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Moyen de règlement — COMPTABILITE_CONCEPTION.md §4.
 *
 * #5272 — ajout des moyens « en ligne » (passerelle de paiement) : les
 * paiements rapprochés par webhook (Chargily DZ / Stripe) portent ces valeurs
 * dans `accounting_payments.method` (colonne string, ajout additif).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
    case Card = 'card';
    case Other = 'other';
    case OnlineChargily = 'online_chargily';
    case OnlineStripe = 'online_stripe';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $method): string => $method->value, self::cases());
    }
}
