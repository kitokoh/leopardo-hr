<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * Exception métier de base du module CRM (canaux).
 *
 * Chaque sous-classe porte un code métier exposé à l'API (ex.
 * CRM_CHANNEL_NOT_CONFIGURED) — le contrôleur traduit le code en message
 * i18n localisé (lang/{fr,en,tr,ar}/crm.php).
 */
abstract class CrmChannelException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function httpStatus(): int
    {
        return 422;
    }
}
