<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\DTOs;

final readonly class CreatePayrollRunDTO
{
    public function __construct(
        public string $companyId,
        public string $period,
        public string $status = 'draft',
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $paymentDate = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'period' => $this->period,
            'status' => $this->status,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'payment_date' => $this->paymentDate,
        ];
    }
}
