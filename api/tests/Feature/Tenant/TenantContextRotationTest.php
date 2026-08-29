<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC02 (issue #5878) — BC-02 Tenant & Isolation, deep-maturity.
 *
 * Verrouille la « rotation de contexte » (backlog BC-02) : basculements
 * rapides et imbriqués entre tenants + middleware de contexte des jobs
 * (TenantScopedJob / EnsureTenantContext) — aucun croisement de données,
 * contexte toujours restauré.
 */
class TenantContextRotationTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @var Company[] */
    private array $companies = [];

    /** @var Employee[] */
    private array $employees = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['alpha', 'beta', 'gamma'] as $i => $slug) {
            /** @var Company $company */
            $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
            /** @var Employee $employee */
            $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
            $this->companies[$slug] = $company;
            $this->employees[$slug] = $employee;
        }
    }

    private int $seedCounter = 0;

    private function seedAbsence(string $slug): void
    {
        $company = $this->companies[$slug];
        $employee = $this->employees[$slug];
        $this->seedCounter++;

        // NB : absence_types.code porte un index UNIQUE GLOBAL (pas par tenant)
        // — découverte de l'audit BC-02 (MATURITY-BC02-TENANT.md, risque R1).
        // Codes distincts ici pour ne pas heurter la contrainte.
        /** @var AbsenceType $type */
        $type = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé '.$slug.' #'.$this->seedCounter,
            'code' => 'CP-'.substr($company->id, 0, 8).'-'.$this->seedCounter,
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'days_count' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
    }

    public function test_rapid_rotation_between_tenants_keeps_data_isolated(): void
    {
        $manager = app(TenantManager::class);

        // Rotation rapide A → B → C → A : chaque écriture reste dans son tenant.
        $manager->withinTenant($this->companies['alpha'], fn () => $this->seedAbsence('alpha'));
        $manager->withinTenant($this->companies['beta'], fn () => $this->seedAbsence('beta'));
        $manager->withinTenant($this->companies['gamma'], fn () => $this->seedAbsence('gamma'));
        $manager->withinTenant($this->companies['alpha'], fn () => $this->seedAbsence('alpha'));

        // Chaque tenant ne voit que ses propres données, même après rotation.
        $manager->withinTenant($this->companies['alpha'], function (): void {
            $this->assertSame(2, Absence::query()->where('company_id', $this->companies['alpha']->id)->count());
            $this->assertSame(0, Absence::query()->where('company_id', $this->companies['beta']->id)->count());
            $this->assertSame(0, Absence::query()->where('company_id', $this->companies['gamma']->id)->count());
        });

        $manager->withinTenant($this->companies['beta'], function (): void {
            $this->assertSame(1, Absence::query()->where('company_id', $this->companies['beta']->id)->count());
            $this->assertSame(0, Absence::query()->where('company_id', $this->companies['alpha']->id)->count());
        });

        // Contexte restauré après la rotation (aucun tenant résiduel).
        $this->assertFalse($manager->hasTenant());
    }

    public function test_ensure_tenant_context_middleware_establishes_and_restores_context(): void
    {
        $company = $this->companies['beta'];
        $manager = app(TenantManager::class);

        $seen = null;
        $job = new class($company->id) implements TenantScopedJob
        {
            public function __construct(private readonly string $companyId) {}

            public function tenantCompanyId(): string
            {
                return $this->companyId;
            }
        };

        (new EnsureTenantContext)->handle($job, function () use (&$seen): void {
            $seen = app(TenantManager::class)->current()?->id;
        });

        // Le job a tourné dans le contexte du tenant B…
        $this->assertSame($company->id, $seen);
        // …et le contexte est restauré après (finally du withinTenant).
        $this->assertFalse($manager->hasTenant());
    }

    public function test_ensure_tenant_context_middleware_releases_when_company_missing(): void
    {
        $job = new class('00000000-0000-0000-0000-000000000000') implements TenantScopedJob
        {
            public function __construct(private readonly string $companyId) {}

            public function tenantCompanyId(): string
            {
                return $this->companyId;
            }

            public bool $released = false;

            public function release(int $delay = 0): void
            {
                $this->released = true;
            }
        };

        (new EnsureTenantContext)->handle($job, fn () => $this->fail('Le job ne doit pas s\'exécuter sans tenant.'));

        // Company introuvable → release (retry), pas d'exécution, pas de crash.
        $this->assertTrue($job->released);
    }
}
