<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Type de poste de cout du calcul rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * Liste NORMATIVE (registre ferme) : chaque poste d'un calcul de cout
 * appartient a l'un de ces types, ce qui rend les agregats comparables entre
 * dossiers et entre devises.
 */
enum TradeCostLineType: string
{
    case PurchasePrice = 'purchase_price';
    case InlandFreightOrigin = 'inland_freight_origin';
    case ExportFees = 'export_fees';
    case InternationalFreight = 'international_freight';
    case Insurance = 'insurance';
    case Handling = 'handling';
    case PortFees = 'port_fees';
    case CustomsDuty = 'customs_duty';
    case Taxes = 'taxes';
    case CustomsBrokerFee = 'customs_broker_fee';
    case InlandFreightDestination = 'inland_freight_destination';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Libelle lisible par defaut (le front peut localiser par cle i18n).
     */
    public function label(): string
    {
        return match ($this) {
            self::PurchasePrice => 'Prix d\'achat',
            self::InlandFreightOrigin => 'Transport interieur origine',
            self::ExportFees => 'Frais a l\'export',
            self::InternationalFreight => 'Fret international',
            self::Insurance => 'Assurance',
            self::Handling => 'Manutention',
            self::PortFees => 'Frais portuaires',
            self::CustomsDuty => 'Droits de douane',
            self::Taxes => 'Taxes',
            self::CustomsBrokerFee => 'Frais de dedouanement',
            self::InlandFreightDestination => 'Transport interieur destination',
            self::Other => 'Autres frais',
        };
    }
}
