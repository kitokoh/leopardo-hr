<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\DTOs;

final class CompleteStepDTO
{
    public function __construct(
        public readonly int    $companyId,
        public readonly string $stepKey,
        public readonly bool   $skipped = false,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id'],
            stepKey:   $data['step_key'],
            skipped:   (bool) ($data['skipped'] ?? false),
            metadata:   $data['metadata'] ?? null,
        );
    }
}
