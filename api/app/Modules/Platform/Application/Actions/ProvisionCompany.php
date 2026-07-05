<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Modules\Platform\Application\DTOs\ProvisionCompanyDTO;
use App\Modules\Platform\Infrastructure\Services\CompanyProvisioningService;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Use Case: Provision a new tenant company (super-admin).
 */
final class ProvisionCompany
{
    public function __construct(
        private readonly CompanyProvisioningService $provisioningService,
    ) {}

    public function execute(ProvisionCompanyDTO $dto): Company
    {
        return $this->provisioningService->provision($dto);
    }
}

