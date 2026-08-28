<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * #5718 — Exceptions métier de la déduplication/fusion CRM.
 */
class CrmMergeException extends RuntimeException
{
    public static function notFound(string $entity): self
    {
        return new self("CRM_{$entity}_NOT_FOUND", 404);
    }

    public static function sameEntity(): self
    {
        return new self('CRM_MERGE_SAME_ENTITY', 422);
    }

    public static function crossTenant(): self
    {
        return new self('CRM_MERGE_CROSS_TENANT', 404);
    }

    public static function invalidPair(string $reason): self
    {
        return new self("CRM_MERGE_INVALID_PAIR: {$reason}", 422);
    }
}
