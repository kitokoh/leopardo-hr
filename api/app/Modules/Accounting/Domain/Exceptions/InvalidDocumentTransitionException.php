<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Transition de statut non autorisée pour un document comptable (issue #5223).
 */
final class InvalidDocumentTransitionException extends DomainException
{
    /**
     * @param  list<string>  $allowed
     */
    public function __construct(string $current, string $requested, array $allowed)
    {
        parent::__construct(
            sprintf(
                'INVALID_DOCUMENT_TRANSITION: %s -> %s (allowed: %s)',
                $current,
                $requested,
                implode(', ', $allowed) ?: 'none'
            ),
            422,
            'INVALID_DOCUMENT_TRANSITION'
        );
    }
}
