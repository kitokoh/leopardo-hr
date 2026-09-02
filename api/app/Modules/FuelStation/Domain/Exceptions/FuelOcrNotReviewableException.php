<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * AI-002 (#6771) — demande OCR non revoyable (statut ≠ needs_review, ou
 * tentative de re-revue). Le contrat API expose le code stable
 * OCR_REQUEST_NOT_REVIEWABLE (409).
 */
class FuelOcrNotReviewableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Cette demande OCR ne peut pas etre revue dans son etat actuel.',
            409,
            'OCR_REQUEST_NOT_REVIEWABLE'
        );
    }
}
