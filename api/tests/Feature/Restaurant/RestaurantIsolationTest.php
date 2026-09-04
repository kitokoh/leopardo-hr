<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Contracts\RestaurantBranchRepositoryInterface;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-108 (#6165) — Harness de test BC-25 : isolation cross-tenant.
 *
 * Test générique de la règle « tenant-safe » de la spec (§1.3 règle 2) :
 * toute donnée créée dans un tenant A est invisible depuis un tenant B —
 * y compris via les repositories (contrat RESTO-215) qui retournent null
 * pour une ressource d'un autre tenant (404 sûr, jamais 403).
 */
class RestaurantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_branch_of_tenant_a_is_invisible_from_tenant_b(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $branchId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return RestaurantBranch::factory()->create(['code' => 'BR-A-001'])->id;
        });

        $visibleInB = app(TenantManager::class)->withinTenant($companyB, function () use ($branchId): bool {
            return RestaurantBranch::query()->whereKey($branchId)->exists();
        });

        $this->assertFalse($visibleInB, 'La branche du tenant A ne doit pas être visible depuis le tenant B.');
    }

    public function test_repository_find_for_company_returns_null_for_foreign_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $branchId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return RestaurantBranch::factory()->create(['code' => 'BR-A-002'])->id;
        });

        /** @var RestaurantBranchRepositoryInterface $repository */
        $repository = app(RestaurantBranchRepositoryInterface::class);

        $found = $repository->findForCompany($branchId, $companyB->id);

        $this->assertNull($found, 'Le repository doit masquer une branche d un autre tenant (404 sûr).');
    }

    public function test_default_branch_resolution_is_tenant_scoped(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($company, function (): void {
            RestaurantBranch::factory()->create(['code' => 'BR-A-003', 'status' => 'active']);
        });

        /** @var RestaurantBranchRepositoryInterface $repository */
        $repository = app(RestaurantBranchRepositoryInterface::class);

        $default = $repository->findDefaultForCompany($company->id);

        $this->assertNotNull($default);
        $this->assertSame($company->id, $default->company_id);
    }
}
