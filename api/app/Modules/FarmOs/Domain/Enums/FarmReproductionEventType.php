<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Type d'evenement de reproduction.
 *
 * Registre FERME et VERSIONNE. `expected_due_at` est une DATE DECLAREE ou
 * calculee a partir d'une duree de gestation SAISIE par l'exploitant : Farm OS
 * ne pretend jamais connaitre la duree de gestation d'une espece donnee.
 */
enum FarmReproductionEventType: string
{
    case Heat = 'heat';
    case Mating = 'mating';
    case PregnancyCheck = 'pregnancy_check';
    case Birth = 'birth';
    case Abortion = 'abortion';
    case DryingOff = 'drying_off';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.reproduction_event.'.$this->value;
    }

    /**
     * L'evenement ouvre-t-il une gestation en cours ?
     */
    public function opensGestation(): bool
    {
        return $this === self::Mating;
    }

    /**
     * L'evenement clot-il une gestation ?
     */
    public function closesGestation(): bool
    {
        return in_array($this, [self::Birth, self::Abortion], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
