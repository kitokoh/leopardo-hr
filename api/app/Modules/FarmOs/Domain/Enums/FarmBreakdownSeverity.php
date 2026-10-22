<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Gravite d'une panne.
 *
 * Registre FERME et VERSIONNE. La gravite est DECLAREE par l'exploitant : Farm
 * OS ne diagnostique jamais une panne (aucun diagnostic mecanique automatise).
 */
enum FarmBreakdownSeverity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.breakdown_severity.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
