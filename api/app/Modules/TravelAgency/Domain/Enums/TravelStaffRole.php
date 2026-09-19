<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * #7638 (TRAVEL-STAFF) — rôle MÉTIER travel d'un employé RH affecté à la
 * verticale. Distinct du RBAC plateforme (`manager_role`) : ce rôle décrit
 * la fonction opérationnelle sur le terrain (manifeste, guichet, contrôle).
 */
enum TravelStaffRole: string
{
    case DRIVER = 'driver';
    case AGENT = 'agent';
    case CONTROLLER = 'controller';
    case OFFICE_MANAGER = 'office_manager';

    public function label(): string
    {
        return match ($this) {
            self::DRIVER => 'Chauffeur',
            self::AGENT => 'Guichetier',
            self::CONTROLLER => 'Contrôleur',
            self::OFFICE_MANAGER => 'Chef de bureau',
        };
    }
}
