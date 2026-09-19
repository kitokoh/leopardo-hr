<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * #7638 (TRAVEL-STAFF) — cycle de vie d'une affectation d'équipage :
 * active tant que l'employé occupe le poste, révoquée sinon (jamais
 * supprimée pour garder l'historique des manifestes passés).
 */
enum TravelStaffAssignmentStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
}
