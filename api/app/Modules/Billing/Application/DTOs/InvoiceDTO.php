<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

use Carbon\Carbon;

final class InvoiceDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly Carbon $dueDate,
        public readonly ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:   (int) $data['company_id'],
            amount:      (float) $data['amount'],
            currency:    $data['currency'] ?? 'DZD',
            status:      $data['status'] ?? 'pending',
            dueDate:     Carbon::parse($data['due_date']),
            description: $data['description'] ?? null,
        );
    }
}
