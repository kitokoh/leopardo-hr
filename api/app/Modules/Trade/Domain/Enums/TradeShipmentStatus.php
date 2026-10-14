<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Etat d'une expedition — BC-36 TRADE, TRADE-004/#8479.
 *
 * `in_customs` est un etat de plein droit : le passage douanier n'est pas une
 * note libre mais une etape suivie du cycle logistique.
 */
enum TradeShipmentStatus: string
{
    case Pending = 'pending';
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case InCustoms = 'in_customs';
    case Delivered = 'delivered';
    case Exception = 'exception';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Exception], true);
    }
}
