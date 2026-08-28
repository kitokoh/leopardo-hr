<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * #5717 — Exceptions métier du cycle de vie des leads CRM.
 */
class CrmLeadException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('CRM_LEAD_NOT_FOUND', 404);
    }

    public static function alreadyConverted(): self
    {
        return new self('CRM_LEAD_ALREADY_CONVERTED', 409);
    }

    public static function noDefaultPipeline(): self
    {
        return new self('CRM_PIPELINE_NOT_FOUND', 422);
    }
}
