<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Fixture — job tenant-scopé de test du contrat TenantManager (issue #5736).
 *
 * Variante de ProbeTenantJob (#5706) : le test qui l'utilise vérifie la
 * RESTAURATION du contexte (search_path + current_company) APRÈS le passage
 * du middleware EnsureTenantContext, pas seulement son établissement.
 */
final class TenantContextProbeJob implements TenantScopedJob
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
