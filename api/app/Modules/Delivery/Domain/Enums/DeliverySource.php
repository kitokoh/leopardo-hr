<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Origine d'une livraison (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * BC-26 est un module de livraison générique : tout tenant qui livre (agence,
 * restaurant, retail, e-commerce, CRM, pharmacie) utilise le même moteur —
 * seule l'origine diffère. `manual` = saisie dispatcher ; les autres sources
 * référencent la commande source via `source_reference`
 * (unique (company_id, source, source_reference) → zéro doublon).
 */
enum DeliverySource: string
{
    case Manual = 'manual';
    case Restaurant = 'restaurant'; // BC-25 RESTAURANT
    case Retail = 'retail'; // BC-17 RETAIL
    case Ecommerce = 'ecommerce'; // BC-14 INTEGRATION
    case Crm = 'crm'; // BC-11 CRM
    case Field = 'field'; // BC-18 FIELD

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
