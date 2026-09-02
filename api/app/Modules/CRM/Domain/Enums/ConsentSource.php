<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Origine d'un consentement CRM — Issue #5722 (traçabilité RGPD).
 */
enum ConsentSource: string
{
    case Form = 'form';
    case Api = 'api';
    case Import = 'import';
    case Manual = 'manual';
    case EmailLink = 'email_link';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
