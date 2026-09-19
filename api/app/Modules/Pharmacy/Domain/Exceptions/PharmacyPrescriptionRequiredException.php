<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * PHARMA-005 (#7802) — le panier contient un produit à ordonnance
 * obligatoire (`prescription_required`) et aucune référence d'ordonnance
 * (`prescription_id`) n'est fournie : la vente est refusée en bloc.
 */
class PharmacyPrescriptionRequiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Un produit du panier exige une ordonnance : fournir prescription_id.',
            422,
            'PHARMACY_PRESCRIPTION_REQUIRED'
        );
    }
}
