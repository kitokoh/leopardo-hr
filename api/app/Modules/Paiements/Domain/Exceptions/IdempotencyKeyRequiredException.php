<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * Cle d'idempotence absente (BC-38, #8682).
 *
 * Toute operation financiere doit etre rejouable sans effet de bord : sans
 * cle, un rejeu reseau creerait un doublon d'ecriture.
 */
class IdempotencyKeyRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Cle d'idempotence obligatoire pour toute operation financiere.");
    }
}
