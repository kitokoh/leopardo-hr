<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Enums;

/**
 * Catégorie de cagnotte (spec §3.1) — affichage public et filtres.
 */
enum FundraisingCategory: string
{
    case MEDICAL = 'medical';
    case EDUCATION = 'education';
    case EMERGENCY = 'emergency';
    case COMMUNITY = 'community';
    case PROJECT = 'project';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MEDICAL => 'Frais médicaux',
            self::EDUCATION => 'Éducation',
            self::EMERGENCY => 'Urgence / sinistre',
            self::COMMUNITY => 'Solidarité communautaire',
            self::PROJECT => 'Projet',
            self::OTHER => 'Autre',
        };
    }
}
