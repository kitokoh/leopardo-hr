<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — T2.1 Mouvement de stock refusé.
 *
 * Le message est un CODE stable (jamais un libellé français en dur) : le front
 * le traduit via le catalogue i18n (PA2-I18N-007 / #5432).
 */
final class FarmStockException extends RuntimeException
{
    public static function quantityMustBePositive(string $quantity): self
    {
        return new self('FARM_STOCK_QUANTITY_NOT_POSITIVE:'.$quantity);
    }

    public static function insufficientStock(int $lotId, string $available, string $requested): self
    {
        return new self(sprintf('FARM_STOCK_INSUFFICIENT:%d:%s:%s', $lotId, $available, $requested));
    }

    public static function lotNotInCompany(int $lotId): self
    {
        return new self('FARM_STOCK_LOT_NOT_IN_COMPANY:'.$lotId);
    }

    public static function inputNotInCompany(int $inputId): self
    {
        return new self('FARM_STOCK_INPUT_NOT_IN_COMPANY:'.$inputId);
    }

    public static function lotRequiredForIssue(): self
    {
        return new self('FARM_STOCK_LOT_REQUIRED_FOR_ISSUE');
    }

    /**
     * Code stable exploitable par le contrôleur pour renvoyer un 422 explicite.
     */
    public function errorCode(): string
    {
        return explode(':', $this->getMessage())[0];
    }
}
