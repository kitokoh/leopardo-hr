<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * #5714 — Exception métier du module CRM (import CSV).
 *
 * Porte un code machine stable (ex. `CRM_IMPORT_NOT_FOUND`,
 * `CRM_IMPORT_ALREADY_COMMITTED`) utilisé par le contrôleur pour produire
 * une réponse HTTP 4xx cohérente (erreurs localisées, cf. ADR-CRM-005).
 */
class CrmImportException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('CRM_IMPORT_NOT_FOUND', 404);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("CRM_IMPORT_INVALID_TRANSITION ({$from} → {$to})", 409);
    }

    public static function alreadyCommitted(): self
    {
        return new self('CRM_IMPORT_ALREADY_COMMITTED', 409);
    }

    public static function alreadyCancelled(): self
    {
        return new self('CRM_IMPORT_ALREADY_CANCELLED', 409);
    }

    public static function invalidFile(string $reason): self
    {
        return new self("CRM_IMPORT_INVALID_FILE: {$reason}", 422);
    }

    public static function noValidRows(): self
    {
        return new self('CRM_IMPORT_NO_VALID_ROWS', 422);
    }

    public static function commitFailed(string $reason): self
    {
        return new self("CRM_IMPORT_COMMIT_FAILED: {$reason}", 500);
    }
}
