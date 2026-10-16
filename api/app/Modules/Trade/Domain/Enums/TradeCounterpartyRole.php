<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Roles metier d'une contrepartie — BC-36 TRADE, TRADE-003/#8478.
 *
 * Une contrepartie porte PLUSIEURS roles simultanement (tableau jsonb
 * `roles`) : un meme acteur est souvent a la fois fabricant et fournisseur,
 * ou transitaire et transporteur. C'est l'architecture extensible qui
 * accueille les espaces prestataires (transporteurs, transitaires, douane,
 * inspection) sans refonte ulterieure.
 */
enum TradeCounterpartyRole: string
{
    case Buyer = 'buyer';
    case Supplier = 'supplier';
    case Manufacturer = 'manufacturer';
    case Wholesaler = 'wholesaler';
    case Carrier = 'carrier';
    case Forwarder = 'forwarder';
    case CustomsBroker = 'customs_broker';
    case Inspector = 'inspector';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Roles autorises a repondre a une demande de cotation transport.
     *
     * @return list<string>
     */
    public static function logisticsRoles(): array
    {
        return [self::Carrier->value, self::Forwarder->value];
    }
}
