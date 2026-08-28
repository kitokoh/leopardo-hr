<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\DTOs;

/**
 * Message email sortant (canal CRM) — Issue #5726.
 */
final class EmailMessage
{
    /**
     * @param  array<string, mixed>  $context  contact_id, campaign_send_id, metadata…
     */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $context = [],
    ) {}
}
