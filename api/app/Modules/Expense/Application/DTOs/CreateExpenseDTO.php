<?php

declare(strict_types=1);

namespace App\Modules\Expense\Application\DTOs;

final class CreateExpenseDTO
{
    public function __construct(
        public readonly int    $employeeId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $currency = 'EUR',
        public readonly array  $items = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            employeeId:  (int) $data['employee_id'],
            title:       $data['title'],
            description: $data['description'] ?? null,
            currency:    $data['currency'] ?? 'EUR',
            items:       $data['items'] ?? [],
        );
    }
}
