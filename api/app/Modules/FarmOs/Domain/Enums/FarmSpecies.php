<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Espece animale.
 *
 * Registre FERME et VERSIONNE : extension par nouvelle case uniquement, jamais
 * de renommage ni de retrait (meme regle que `FarmActivity`).
 *
 * Aucun libelle utilisateur ici (garde i18n PA2-I18N-007) : la traduction se
 * fait cote front via {@see translationKey()}.
 */
enum FarmSpecies: string
{
    case Cattle = 'cattle';
    case Sheep = 'sheep';
    case Goat = 'goat';
    case Poultry = 'poultry';
    case Pig = 'pig';
    case Bee = 'bee';
    case Fish = 'fish';
    case Rabbit = 'rabbit';
    case Horse = 'horse';
    case Camel = 'camel';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.species.'.$this->value;
    }

    /**
     * Productions typiques de l'espece. Sert UNIQUEMENT a proposer des choix a
     * la saisie : Farm OS n'enregistre jamais une production non declaree.
     *
     * @return list<string>
     */
    public function typicalProductions(): array
    {
        return match ($this) {
            self::Cattle, self::Sheep, self::Goat, self::Camel => ['milk', 'meat', 'wool'],
            self::Poultry => ['eggs', 'meat'],
            self::Bee => ['honey'],
            self::Fish => ['meat'],
            self::Pig, self::Rabbit => ['meat'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
