<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-003 (#7800) — le stock disponible (lots non périmés) ne couvre pas
 * la quantité demandée : la délivrance FEFO est refusée EN BLOC (aucun effet
 * partiel, transaction annulée).
 */
class PharmacyInsufficientStockException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Stock insuffisant : les lots non perimes ne couvrent pas la quantite demandee.',
            422,
            'PHARMACY_INSUFFICIENT_STOCK'
        );
    }
}
