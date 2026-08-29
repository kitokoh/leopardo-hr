<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;

/**
 * Fixture — événement tenant-scopé de test (issue #5706).
 */
final class ProbeTenantEvent implements TenantScopedEvent
{
    public function __construct(private readonly ?string $companyId) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
