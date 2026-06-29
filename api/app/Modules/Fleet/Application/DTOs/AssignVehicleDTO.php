<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Application\DTOs;

use Carbon\Carbon;

final class AssignVehicleDTO
{
    public function __construct(
        public readonly int    $vehicleId,
        public readonly int    $employeeId,
        public readonly int    $companyId,
        public readonly Carbon $startDate,
        public readonly ?Carbon $endDate = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId:  (int) $data['vehicle_id'],
            employeeId: (int) $data['employee_id'],
            companyId:  (int) $data['company_id'],
            startDate:  Carbon::parse($data['start_date']),
            endDate:    isset($data['end_date']) ? Carbon::parse($data['end_date']) : null,
            notes:      $data['notes'] ?? null,
        );
    }
}
