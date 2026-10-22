<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T2.2 Saisie de materiel refusee.
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmEquipmentException extends RuntimeException
{
    public static function equipmentNotInCompany(int $equipmentId): self
    {
        return new self('FARM_EQUIPMENT_NOT_IN_COMPANY:'.$equipmentId);
    }

    /**
     * Un compteur ne recule pas : une valeur inferieure a la derniere lecture
     * connue est refusee (sauf remplacement explicite du compteur).
     */
    public static function meterRegression(string $previous, string $given): self
    {
        return new self(sprintf('FARM_EQUIPMENT_METER_REGRESSION:%s:%s', $previous, $given));
    }

    public static function meterNotTracked(int $equipmentId): self
    {
        return new self('FARM_EQUIPMENT_METER_NOT_TRACKED:'.$equipmentId);
    }

    public static function meterTypeMismatch(string $expected, string $given): self
    {
        return new self(sprintf('FARM_EQUIPMENT_METER_TYPE_MISMATCH:%s:%s', $expected, $given));
    }

    public static function maintenanceAlreadyClosed(int $maintenanceId): self
    {
        return new self('FARM_EQUIPMENT_MAINTENANCE_ALREADY_CLOSED:'.$maintenanceId);
    }

    public static function equipmentRetired(int $equipmentId): self
    {
        return new self('FARM_EQUIPMENT_RETIRED:'.$equipmentId);
    }

    public static function invalidCost(string $amount): self
    {
        return new self('FARM_EQUIPMENT_INVALID_COST:'.$amount);
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
