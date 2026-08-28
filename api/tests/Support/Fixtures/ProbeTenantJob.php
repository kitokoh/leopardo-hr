<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Fixture — job tenant-scopé de test (issue #5706).
 *
 * Implémente le contrat TenantScopedJob avec le middleware EnsureTenantContext
 * pour vérifier le verrouillage du contexte tenant sur la file (search_path +
 * current_company établis avant handle(), release si compagnie absente).
 */
final class ProbeTenantJob implements TenantScopedJob
{
    use InteractsWithQueue;

    public function __construct(private readonly string $companyId) {}

    public function tenantCompanyId(): string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(): void {}
}
