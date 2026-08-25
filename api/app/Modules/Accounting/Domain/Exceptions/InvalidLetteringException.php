<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Levée quand un lettrage viole les gardes structurelles : moins de deux
 * écritures, comptes différents, ou écriture déjà lettrée avec une autre
 * lettre (code LETTERING_ALREADY_USED). Issue #5422.
 */
class InvalidLetteringException extends DomainException
{
    public function __construct(
        string $message = 'Lettrage invalide : au moins deux écritures du même compte, non déjà lettrées, sont requises.',
        string $errorCode = 'LETTERING_INVALID',
    ) {
        parent::__construct($message, 422, $errorCode);
    }
}
