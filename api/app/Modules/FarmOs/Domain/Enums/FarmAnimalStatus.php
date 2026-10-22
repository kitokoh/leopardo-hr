<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Statut d'un animal ou d'un lot.
 *
 * Registre FERME et VERSIONNE. Un animal `sold` / `dead` / `culled` reste dans
 * le registre (tracabilite) mais ne compte plus dans l'effectif PRESENT.
 */
enum FarmAnimalStatus: string
{
    case Active = 'active';
    case Sold = 'sold';
    case Dead = 'dead';
    case Transferred = 'transferred';
    case Culled = 'culled';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.animal_status.'.$this->value;
    }

    /**
     * L'animal compte-t-il dans l'effectif PRESENT de l'exploitation ?
     */
    public function isPresent(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
