<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T3.1 Saisie de cheptel refusee.
 *
 * Le message est un CODE stable (jamais un libelle francais en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmLivestockException extends RuntimeException
{
    public static function batchNotInCompany(int $batchId): self
    {
        return new self('FARM_LIVESTOCK_BATCH_NOT_IN_COMPANY:'.$batchId);
    }

    public static function animalNotInCompany(int $animalId): self
    {
        return new self('FARM_LIVESTOCK_ANIMAL_NOT_IN_COMPANY:'.$animalId);
    }

    public static function quantityMustBePositive(string $quantity): self
    {
        return new self('FARM_LIVESTOCK_QUANTITY_NOT_POSITIVE:'.$quantity);
    }

    /**
     * Une sortie d'effectif ne peut pas depasser l'effectif PRESENT : on refuse
     * plutot que de laisser un effectif negatif. C'est la garde qui empeche une
     * meme tete d'etre comptee deux fois (vente + mortalite).
     */
    public static function insufficientHeadCount(int $batchId, int $available, int $requested): self
    {
        return new self(sprintf('FARM_LIVESTOCK_INSUFFICIENT_HEAD:%d:%d:%d', $batchId, $available, $requested));
    }

    public static function animalAlreadyLeft(int $animalId): self
    {
        return new self('FARM_LIVESTOCK_ANIMAL_ALREADY_LEFT:'.$animalId);
    }

    public static function speciesMismatch(string $expected, string $given): self
    {
        return new self(sprintf('FARM_LIVESTOCK_SPECIES_MISMATCH:%s:%s', $expected, $given));
    }

    public static function invalidQuantity(string $quantity): self
    {
        return new self('FARM_LIVESTOCK_INVALID_QUANTITY:'.$quantity);
    }

    /**
     * Code stable exploitable par le controleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
