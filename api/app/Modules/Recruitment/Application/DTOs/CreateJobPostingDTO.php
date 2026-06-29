<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\DTOs;

final class CreateJobPostingDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $contractType,
        public readonly ?string $location = null,
        public readonly ?string $salaryRange = null,
        public readonly ?string $department = null,
        public readonly bool   $isPublished = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId:    (int) $data['company_id'],
            title:        $data['title'],
            description:  $data['description'],
            contractType: $data['contract_type'],
            location:     $data['location'] ?? null,
            salaryRange:  $data['salary_range'] ?? null,
            department:   $data['department'] ?? null,
            isPublished:  (bool) ($data['is_published'] ?? false),
        );
    }
}
