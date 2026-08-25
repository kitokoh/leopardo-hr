<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

/**
 * Levée quand une ou plusieurs écritures sélectionnées pour le lettrage sont
 * déjà lettrées avec une AUTRE lettre (code LETTERING_ALREADY_USED).
 *
 * Sous-classe d'InvalidLetteringException : le contrôleur peut distinguer le
 * cas sans lire le code d'erreur (pattern d'erreur du module, issue #5422).
 */
class LetteringAlreadyUsedException extends InvalidLetteringException
{
    public function __construct()
    {
        parent::__construct(
            'Lettrage impossible : une ou plusieurs écritures sont déjà lettrées avec une autre lettre.',
            'LETTERING_ALREADY_USED',
        );
    }
}
