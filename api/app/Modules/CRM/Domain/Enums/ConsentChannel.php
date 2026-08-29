<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * Canal de communication CRM — Issue #5722 (consentements).
 */
enum ConsentChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Push = 'push';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }
}
