<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5272 — Le document ne peut pas être payé en ligne : brouillon/annulé
 * (jamais émis) ou déjà soldé (solde ≤ 0). Les règles de transition du
 * workflow #5223 sont préservées.
 */
class DocumentNotSendableException extends DomainException
{
    public function __construct(string $status)
    {
        parent::__construct(
            sprintf('DOCUMENT_NOT_SENDABLE: document status "%s" cannot be paid online', $status),
            422,
            'DOCUMENT_NOT_SENDABLE'
        );
    }
}
