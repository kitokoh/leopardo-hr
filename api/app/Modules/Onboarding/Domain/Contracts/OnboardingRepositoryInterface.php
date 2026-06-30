<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Contracts;

interface OnboardingRepositoryInterface
{
    public function findByCompany(int $companyId): ?object;

    public function getProgress(int $companyId): array;

    public function markStepComplete(int $companyId, string $stepKey): void;

    public function markStepSkipped(int $companyId, string $stepKey): void;
}
