<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-005 (#7802) — le panier contient un produit sous ordonnance
 * (`prescription_required` ou `is_controlled`) et aucune `prescription_id`
 * n'est fournie : la vente est refusée.
 */
class PharmacyPrescriptionRequiredException extends DomainException
{
    public function __construct(string $productName)
    {
        parent::__construct(
            sprintf('Le produit « %s » exige une ordonnance : fournir prescription_id.', $productName),
            422,
            'PHARMACY_PRESCRIPTION_REQUIRED'
        );
    }
}
