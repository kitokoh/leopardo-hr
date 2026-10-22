<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.2 Type de mouvement de stock d'une recolte.
 *
 * Registre FERME et VERSIONNE. Le SENS du mouvement est porte par le type,
 * jamais par le signe d'une quantite : une quantite de mouvement est toujours
 * POSITIVE. C'est ce qui rend impossible de « sortir en negatif » pour
 * contourner la garde de stock.
 */
enum FarmStorageMovementType: string
{
    case In = 'in';
    case Transfer = 'transfer';
    case Loss = 'loss';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Consumption = 'consumption';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.storage_movement_type.'.$this->value;
    }

    /**
     * Le mouvement RETIRE-t-il de la quantite restante du lot ?
     *
     * `transfer` ne retire rien : la marchandise change d'emplacement, elle
     * reste dans le lot. `adjustment` est traite a part (il peut aller dans les
     * deux sens) et n'est donc pas « une sortie » au sens de cette methode.
     */
    public function isOutbound(): bool
    {
        return match ($this) {
            self::Loss, self::Sale, self::Consumption => true,
            self::In, self::Transfer, self::Adjustment => false,
        };
    }

    /**
     * Le mouvement est-il une contrepartie commerciale ? Dans ce cas il doit
     * referencer un document du service de VENTES EXISTANT : Farm OS ne cree
     * aucun document commercial et aucun systeme financier parallele.
     */
    public function requiresSaleReference(): bool
    {
        return $this === self::Sale;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
