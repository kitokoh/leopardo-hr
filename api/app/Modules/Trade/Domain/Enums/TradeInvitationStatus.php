<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Etat d'une invitation fournisseur — BC-36 TRADE, TRADE-003/#8478.
 *
 * Chaque invitation porte son propre etat : une RFQ diffusee a 5 fournisseurs
 * a 5 etats independants, ce qui permet de relancer precisement ceux qui n'ont
 * pas encore repondu.
 */
enum TradeInvitationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Responded = 'responded';
    case Declined = 'declined';
    case Expired = 'expired';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Responded, self::Declined, self::Expired], true);
    }
}
