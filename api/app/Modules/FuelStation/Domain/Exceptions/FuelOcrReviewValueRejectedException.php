<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * AI-002 (#6771) — valeur d'un relevé OCR refusée par
 * MeterReadingService::record() lors d'une revue acceptée (valeur
 * négative, références incohérentes…). Le contrat API expose le code
 * stable REVIEW_VALUE_REJECTED (422) — aucun relevé n'est enregistré.
 */
class FuelOcrReviewValueRejectedException extends DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            $reason,
            422,
            'REVIEW_VALUE_REJECTED'
        );
    }
}
