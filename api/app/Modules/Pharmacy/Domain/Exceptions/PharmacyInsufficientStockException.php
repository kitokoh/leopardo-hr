<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-003 (#7800) — stock disponible (lots non périmés) insuffisant pour
 * la délivrance demandée. Transaction annulée : aucun effet partiel.
 */
class PharmacyInsufficientStockException extends DomainException
{
    public function __construct(int $productId, int $requested, int $available)
    {
        parent::__construct(
            sprintf(
                'Stock insuffisant pour le produit #%d : %d demandé, %d disponible (lots non périmés).',
                $productId,
                $requested,
                $available
            ),
            422,
            'PHARMACY_INSUFFICIENT_STOCK'
        );
    }
}
