<?php

namespace App\Modules\Payroll\Application\DTOs;

final readonly class GeneratePayrollDTO
{
    public function __construct(
        public string $companyId,
        public string $period,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'period' => $this->period,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
