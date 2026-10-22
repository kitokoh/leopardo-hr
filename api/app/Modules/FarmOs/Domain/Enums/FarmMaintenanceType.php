<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Nature d'un entretien.
 *
 * Registre FERME et VERSIONNE. `preventive` = entretien planifie (vidange,
 * revision) ; `corrective` = reparation consecutive a une panne.
 */
enum FarmMaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.maintenance_type.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
