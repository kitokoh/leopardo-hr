<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * Type de pièce d'identité d'un passager (TRAVEL-209, issue #6022).
 */
enum DocumentType: string
{
    case NATIONAL_ID = 'national_id';
    case PASSPORT = 'passport';
    case BIRTH_CERTIFICATE = 'birth_certificate';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NATIONAL_ID => 'Carte nationale d\'identité',
            self::PASSPORT => 'Passeport',
            self::BIRTH_CERTIFICATE => 'Acte de naissance',
            self::OTHER => 'Autre',
        };
    }
}
