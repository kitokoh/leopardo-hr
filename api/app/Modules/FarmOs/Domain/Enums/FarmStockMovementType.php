<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.1 Type de mouvement de stock d'intrant.
 *
 * Registre FERMÉ et VERSIONNÉ. Le SENS du mouvement est porté par le type, et
 * jamais par le signe de la quantité : une quantité de mouvement est toujours
 * positive (contrainte de lisibilité et de cohérence du journal).
 */
enum FarmStockMovementType: string
{
    /** Entrée : réception d'un lot (achat, don, retour). */
    case Receipt = 'receipt';

    /** Sortie : consommation sur une opération, vente, perte. */
    case Issue = 'issue';

    /** Ajustement : correction d'inventaire (à la hausse ou à la baisse). */
    case Adjustment = 'adjustment';

    /**
     * Le mouvement augmente-t-il la quantité du lot ?
     *
     * Un ajustement est AMBIGU par nature : c'est `quantity_after` qui tranche,
     * pas le type. On renvoie donc `null` pour forcer l'appelant à comparer.
     */
    public function increasesStock(): ?bool
    {
        return match ($this) {
            self::Receipt => true,
            self::Issue => false,
            self::Adjustment => null,
        };
    }

    /**
     * Clé i18n (front) — jamais de libellé en dur côté backend.
     */
    public function translationKey(): string
    {
        return 'farm.stock_movement.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
