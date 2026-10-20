<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\DTOs;

/**
 * Payload de webhook entrant (BC-38, #8683).
 */
final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $externalReference,
        public string $eventType,
        public array $body = [],
        public array $config = [],
    ) {}
}
