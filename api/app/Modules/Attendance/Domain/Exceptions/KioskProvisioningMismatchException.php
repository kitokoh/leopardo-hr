<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #7962 (sous-tache) — le kiosque vient d'etre cree mais n'est pas resolvable
 * dans le contexte des routes publiques kiosque.
 *
 * Reproduit en production le 2026-09-01 : 1 kiosque sur 3 cree via
 * `POST /kiosks` devenait introuvable — 404 permanent sur roster malgre un
 * device_code et un sync_token valides. La ligne avait ete ecrite hors du
 * search_path que les routes publiques utilisent.
 *
 * Le fail-closed est deliberе : rendre des credentials inutilisables ferait
 * echouer l'appareil devant l'employe, sur site. Mieux vaut un 500 immediat,
 * trace, qu'un kiosque fantome.
 */
class KioskProvisioningMismatchException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Le kiosque vient d\'etre cree mais n\'est pas resolvable par les routes publiques : credentials non delivres.',
            500
        );
    }

    public function errorCode(): string
    {
        return 'KIOSK_PROVISIONING_FAILED';
    }
}
