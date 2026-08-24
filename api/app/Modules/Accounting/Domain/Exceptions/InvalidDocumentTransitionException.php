<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * Transition de statut non autorisée pour un document comptable (issue #5223).
 */
final class InvalidDocumentTransitionException extends RuntimeException
{
    /**
     * @param  list<string>  $allowed
     */
    public function __construct(string $current, string $requested, array $allowed)
    {
        parent::__construct(sprintf(
            'INVALID_DOCUMENT_TRANSITION: %s -> %s (autorisé : %s)',
            $current,
            $requested,
            implode(', ', $allowed) ?: 'aucune',
        ));
    }
}
