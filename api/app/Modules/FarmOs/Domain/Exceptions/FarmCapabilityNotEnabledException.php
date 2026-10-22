<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — Capacité (outil métier) non activée pour l'exploitation.
 *
 * Levée par {@see \App\Modules\FarmOs\Domain\Services\FarmCapabilityGate} et
 * convertie en 403 par les contrôleurs : un outil non activé n'est jamais
 * accessible, même par appel direct de l'API.
 */
class FarmCapabilityNotEnabledException extends RuntimeException
{
    public static function forCapability(string $capability): self
    {
        return new self(sprintf('Farm capability [%s] is not enabled for this company.', $capability));
    }
}
