<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Type de document commercial — BC-36 TRADE, TRADE-005/#8480.
 *
 * La liste est NORMATIVE : elle sert a la checklist documentaire douaniere
 * (TRADE-007) autant qu'au rangement des pieces du dossier.
 */
enum TradeDocumentType: string
{
    case ProformaInvoice = 'proforma_invoice';
    case CommercialInvoice = 'commercial_invoice';
    case PackingList = 'packing_list';
    case BillOfLading = 'bill_of_lading';
    case AirwayBill = 'airway_bill';
    case CertificateOfOrigin = 'certificate_of_origin';
    case CertificateOfConformity = 'certificate_of_conformity';
    case InsuranceCertificate = 'insurance_certificate';
    case CustomsDeclaration = 'customs_declaration';
    case Contract = 'contract';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ProformaInvoice => 'Facture pro forma',
            self::CommercialInvoice => 'Facture commerciale',
            self::PackingList => 'Liste de colisage',
            self::BillOfLading => 'Connaissement (B/L)',
            self::AirwayBill => 'Lettre de transport aerien (LTA)',
            self::CertificateOfOrigin => 'Certificat d\'origine',
            self::CertificateOfConformity => 'Certificat de conformite',
            self::InsuranceCertificate => 'Certificat d\'assurance',
            self::CustomsDeclaration => 'Declaration douaniere',
            self::Contract => 'Contrat',
            self::Other => 'Autre document',
        };
    }
}
