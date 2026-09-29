<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use RuntimeException;

/**
 * Transition de statut de demande de devis refusee (BC-28 CATALOG, #6885).
 *
 * Matrice de transitions stricte (spec §7) : statut terminal immuable, ou
 * transition absente de `CatalogInquiryStatus::allowedTransitions()`. Le
 * statut courant voyage avec l'exception pour que la couche HTTP compose la
 * meme reponse 422 qu'avant l'extraction (BOS-024f, #8217).
 */
final class InvalidInquiryStatusTransitionException extends RuntimeException
{
    public function __construct(private readonly string $currentStatus)
    {
        parent::__construct('INVALID_INQUIRY_STATUS_TRANSITION');
    }

    public function currentStatus(): string
    {
        return $this->currentStatus;
    }
}
