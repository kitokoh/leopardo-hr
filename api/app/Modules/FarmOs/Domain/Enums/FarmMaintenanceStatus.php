<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Etat d'un entretien.
 *
 * Registre FERME et VERSIONNE. Un entretien `done` est CLOS : on ne le rouvre
 * pas, on en planifie un nouveau (historique de maintenance fiable).
 */
enum FarmMaintenanceStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.maintenance_status.'.$this->value;
    }

    public function isTerminal(): bool
    {
        return $this !== self::Planned;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
