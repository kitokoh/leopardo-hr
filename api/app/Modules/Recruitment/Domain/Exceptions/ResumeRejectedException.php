<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Exceptions;

use App\Modules\Recruitment\Domain\ValueObjects\ResumeScanVerdict;
use RuntimeException;

/**
 * BC-10 (#9217) — le CV a été refusé par la politique antivirus.
 *
 * Porte le verdict pour que le contrôleur puisse répondre sans avoir à
 * re-deviner la cause, et pour que le message affiché au candidat reste
 * générique (on ne révèle pas la signature détectée à un tiers : c'est une
 * information d'exploitation, pas une information candidat).
 */
final class ResumeRejectedException extends RuntimeException
{
    public function __construct(public readonly ResumeScanVerdict $verdict)
    {
        parent::__construct(__('errors.RESUME_REJECTED'));
    }
}
