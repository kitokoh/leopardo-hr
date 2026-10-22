<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Type de production animale.
 *
 * Registre FERME et VERSIONNE. Farm OS n'enregistre que des productions
 * DECLAREES : aucune estimation automatique de rendement.
 */
enum FarmProductionType: string
{
    case Milk = 'milk';
    case Eggs = 'eggs';
    case Wool = 'wool';
    case Honey = 'honey';
    case Meat = 'meat';
    case Manure = 'manure';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.production_type.'.$this->value;
    }

    /**
     * Unite de saisie la plus courante. Sert de valeur par defaut PROPOSEE :
     * l'exploitant peut toujours la corriger.
     */
    public function defaultUnit(): string
    {
        return match ($this) {
            self::Milk => 'l',
            self::Eggs => 'unit',
            self::Wool, self::Honey, self::Meat => 'kg',
            self::Manure => 't',
            default => 'unit',
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
