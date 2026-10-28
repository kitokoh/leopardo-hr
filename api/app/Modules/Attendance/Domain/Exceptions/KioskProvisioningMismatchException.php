<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use RuntimeException;

/**
 * #6678 — le kiosque vient d'être écrit mais n'est pas résolvable par les
 * routes publiques (fuite de `search_path` sur worker partagé).
 *
 * Exception de domaine dédiée plutôt qu'un `abort(500)` : le contrôleur choisit
 * le code HTTP, le domaine nomme la panne. Un `abort()` enfoui dans une couche
 * profonde est invisible au code appelant et indétectable en test sans passer
 * par la pile HTTP complète.
 */
final class KioskProvisioningMismatchException extends RuntimeException
{
    public function __construct(string $message = 'KIOSK_PROVISIONING_FAILED')
    {
        parent::__construct($message);
    }
}
