<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\DTOs;

/**
 * Résultat d'envoi d'un email (canal CRM) — Issue #5726.
 */
final class EmailDeliveryResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function sent(string $messageId): self
    {
        return new self('sent', $messageId);
    }

    public static function failed(string $error): self
    {
        return new self('failed', null, $error);
    }

    public static function suppressed(string $reason): self
    {
        return new self('suppressed', null, $reason);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'sent';
    }
}
