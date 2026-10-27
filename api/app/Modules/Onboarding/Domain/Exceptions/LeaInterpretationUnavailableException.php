<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use RuntimeException;

/**
 * #8229 (BOS-041) — la description libre n'est pas interpretable en l'etat.
 *
 * Porte un **code machine stable** (`reason_code`) et non un message
 * utilisateur : la traduction en texte affichable appartient a la couche
 * interface, et un code stable permet a l'appelant de decider entre
 * « repose la question » (`injection_detected`, `description_too_short`) et
 * « bascule sur l'interview » (`llm_unavailable`).
 */
final class LeaInterpretationUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
    ) {
        parent::__construct("Lea interpretation unavailable: {$reasonCode}");
    }
}
