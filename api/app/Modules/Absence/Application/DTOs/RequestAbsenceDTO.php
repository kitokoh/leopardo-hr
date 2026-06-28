<?php

declare(strict_types=1);

namespace App\Modules\Absence\Application\DTOs;

use Carbon\Carbon;

final class RequestAbsenceDTO
{
    public function __construct(
        public readonly int    $employeeId,
        public readonly int    $absenceTypeId,
        public readonly Carbon $startDate,
        public readonly Carbon $endDate,
        public readonly ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            employeeId:    (int) $data['employee_id'],
            absenceTypeId: (int) $data['absence_type_id'],
            startDate:     Carbon::parse($data['start_date']),
            endDate:       Carbon::parse($data['end_date']),
            reason:        $data['reason'] ?? null,
        );
    }
}
