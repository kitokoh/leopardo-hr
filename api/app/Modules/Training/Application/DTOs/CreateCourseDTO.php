<?php

declare(strict_types=1);

namespace App\Modules\Training\Application\DTOs;

use Carbon\Carbon;

final class CreateCourseDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $format,
        public readonly ?int   $durationHours = null,
        public readonly ?Carbon $startsAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:    (int) $data['company_id'],
            title:        $data['title'],
            description:  $data['description'],
            format:       $data['format'] ?? 'in_person',
            durationHours:isset($data['duration_hours']) ? (int) $data['duration_hours'] : null,
            startsAt:     isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null,
        );
    }
}
