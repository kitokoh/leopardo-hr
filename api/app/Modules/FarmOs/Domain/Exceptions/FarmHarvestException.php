<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T3.2 Saisie de recolte / stockage refusee.
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmHarvestException extends RuntimeException
{
    public static function lotNotInCompany(int $lotId): self
    {
        return new self('FARM_HARVEST_LOT_NOT_IN_COMPANY:'.$lotId);
    }

    public static function locationNotInCompany(int $locationId): self
    {
        return new self('FARM_HARVEST_LOCATION_NOT_IN_COMPANY:'.$locationId);
    }

    public static function quantityMustBePositive(string $quantity): self
    {
        return new self('FARM_HARVEST_QUANTITY_NOT_POSITIVE:'.$quantity);
    }

    /**
     * GARDE ANTI-DOUBLE-COMPTAGE : une sortie ne peut pas depasser la quantite
     * restante du lot. Sans cette garde, la meme recolte pourrait etre vendue
     * deux fois et le reporting afficherait un chiffre d'affaires fictif.
     */
    public static function insufficientStock(int $lotId, string $available, string $requested): self
    {
        return new self(sprintf('FARM_HARVEST_INSUFFICIENT_STOCK:%d:%s:%s', $lotId, $available, $requested));
    }

    public static function lotAlreadyClosed(int $lotId): self
    {
        return new self('FARM_HARVEST_LOT_ALREADY_CLOSED:'.$lotId);
    }

    /**
     * Une vente doit referencer un document du service de ventes EXISTANT :
     * Farm OS ne fabrique jamais de document commercial.
     */
    public static function saleReferenceRequired(): self
    {
        return new self('FARM_HARVEST_SALE_REFERENCE_REQUIRED');
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self('FARM_HARVEST_INVALID_QUANTITY:'.$quantity);
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
