<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\DTOs;

/**
 * Demande de retrait (BC-38, #8683).
 */
final readonly class CashOutRequest
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $companyId,
        public int $amount,
        public string $currency,
        public string $idempotencyKey,
        public array $config = [],
        public array $metadata = [],
    ) {}
}
