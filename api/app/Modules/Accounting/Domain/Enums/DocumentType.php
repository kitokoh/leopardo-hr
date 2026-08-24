<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Type de document comptable — COMPTABILITE_CONCEPTION.md §4 (table unique, type discriminé).
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Proforma = 'proforma';
    case Quote = 'quote';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case Receipt = 'receipt';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
