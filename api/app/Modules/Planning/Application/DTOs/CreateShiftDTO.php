<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\DTOs;

use Carbon\Carbon;

final class CreateShiftDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly int    $employeeId,
        public readonly Carbon $startAt,
        public readonly Carbon $endAt,
        public readonly ?string $label = null,
        public readonly ?string $color = null,
        public readonly ?int    $siteId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:  (int) $data['company_id'],
            employeeId: (int) $data['employee_id'],
            startAt:    Carbon::parse($data['start_at']),
            endAt:      Carbon::parse($data['end_at']),
            label:      $data['label'] ?? null,
            color:      $data['color'] ?? null,
            siteId:     isset($data['site_id']) ? (int) $data['site_id'] : null,
        );
    }
}
