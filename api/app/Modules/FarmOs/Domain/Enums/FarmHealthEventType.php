<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Type d'evenement sanitaire.
 *
 * Registre FERME et VERSIONNE. Farm OS ENREGISTRE ce que l'exploitant declare :
 * il ne diagnostique jamais, ne prescrit jamais et ne remplace jamais un
 * professionnel qualifie (exigence explicite de la mission).
 */
enum FarmHealthEventType: string
{
    case Vaccination = 'vaccination';
    case Treatment = 'treatment';
    case Deworming = 'deworming';
    case Checkup = 'checkup';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.health_event.'.$this->value;
    }

    /**
     * Un delai d'attente (withdrawal) est pertinent pour les traitements et
     * vermifuges : Farm OS le STOCKE tel que declare, sans le calculer.
     */
    public function hasWithdrawalPeriod(): bool
    {
        return in_array($this, [self::Treatment, self::Deworming], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
