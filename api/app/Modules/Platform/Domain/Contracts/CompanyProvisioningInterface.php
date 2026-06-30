<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Contracts;

interface CompanyProvisioningInterface
{
    public function provision(int $companyId, string $plan): void;

    public function deprovision(int $companyId): void;

    public function getStatus(int $companyId): array;
}
