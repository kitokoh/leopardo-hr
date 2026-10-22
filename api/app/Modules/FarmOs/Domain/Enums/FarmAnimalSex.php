<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Sexe d'un animal.
 *
 * `Unknown` est une valeur de plein droit : sur un lot non identifie
 * individuellement, le sexe n'est souvent pas connu, et Farm OS ne le devine
 * jamais.
 */
enum FarmAnimalSex: string
{
    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.animal_sex.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
