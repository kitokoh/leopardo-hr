<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1917 — TaxRatePolicy : porte rôle des taux légaux (tax_slabs +
 * social_contributions).
 *
 * L'isolation tenant (ligne d'une autre entreprise → 404) reste assurée par
 * le scope global `BelongsToCompany` et les gardes 404 des contrôleurs —
 * comportement historique préservé ; la policy porte la porte rôle.
 */
class TaxRatePolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected SuperAdmin $superAdmin;

    protected Employee $manager;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-policy-taxrate@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    private function makeSlab(string $companyId, string $status = TaxSlab::STATUS_DRAFT): TaxSlab
    {
        /** @var TaxSlab $slab */
        $slab = TaxSlab::create([
            'company_id' => $companyId,
            'country_code' => 'DZ',
            'name' => 'Tranche test',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 23,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
            'status' => $status,
        ]);

        return $slab;
    }

    private function makeContribution(string $companyId, string $status = SocialContribution::STATUS_DRAFT): SocialContribution
    {
        /** @var SocialContribution $contribution */
        $contribution = SocialContribution::create([
            'company_id' => $companyId,
            'country_code' => 'DZ',
            'name' => 'CNAS test',
            'code' => 'cnas-policy-'.uniqid(),
            'type' => 'employee',
            'rate' => 9,
            'effective_from' => '2026-01-01',
            'status' => $status,
        ]);

        return $contribution;
    }

    public function test_manager_can_manage_tax_rates(): void
    {
        $slab = $this->makeSlab((string) $this->company->id);
        $contribution = $this->makeContribution((string) $this->company->id);

        $this->assertTrue($this->manager->can('viewAny', TaxSlab::class));
        $this->assertTrue($this->manager->can('create', TaxSlab::class));
        $this->assertTrue($this->manager->can('view', $slab));
        $this->assertTrue($this->manager->can('update', $slab));
        $this->assertTrue($this->manager->can('delete', $slab));
        $this->assertTrue($this->manager->can('submit', $slab));
        $this->assertTrue($this->manager->can('history', $slab));

        $this->assertTrue($this->manager->can('viewAny', SocialContribution::class));
        $this->assertTrue($this->manager->can('create', SocialContribution::class));
        $this->assertTrue($this->manager->can('view', $contribution));
        $this->assertTrue($this->manager->can('update', $contribution));
        $this->assertTrue($this->manager->can('delete', $contribution));
        $this->assertTrue($this->manager->can('submit', $contribution));
        $this->assertTrue($this->manager->can('history', $contribution));
    }

    public function test_employee_cannot_manage_tax_rates(): void
    {
        $slab = $this->makeSlab((string) $this->company->id);

        $this->assertFalse($this->employee->can('viewAny', TaxSlab::class));
        $this->assertFalse($this->employee->can('create', TaxSlab::class));
        $this->assertFalse($this->employee->can('view', $slab));
        $this->assertFalse($this->employee->can('update', $slab));
        $this->assertFalse($this->employee->can('delete', $slab));
        $this->assertFalse($this->employee->can('submit', $slab));
        $this->assertFalse($this->employee->can('history', $slab));

        $this->assertFalse($this->employee->can('viewAny', SocialContribution::class));
        $this->assertFalse($this->employee->can('update', $this->makeContribution((string) $this->company->id)));
    }

    public function test_only_super_admin_can_approve_or_reject(): void
    {
        $pendingSlab = $this->makeSlab((string) $this->company->id, TaxSlab::STATUS_PENDING);
        $pendingContribution = $this->makeContribution((string) $this->company->id, SocialContribution::STATUS_PENDING);

        // SuperAdmin plateforme : approbation/rejet autorisés.
        $this->assertTrue($this->superAdmin->can('approve', $pendingSlab));
        $this->assertTrue($this->superAdmin->can('reject', $pendingSlab));
        $this->assertTrue($this->superAdmin->can('approve', $pendingContribution));
        $this->assertTrue($this->superAdmin->can('reject', $pendingContribution));

        // Manager tenant : jamais (le statut de la ligne est sans effet).
        $this->assertFalse($this->manager->can('approve', $pendingSlab));
        $this->assertFalse($this->manager->can('reject', $pendingSlab));
        $this->assertFalse($this->employee->can('approve', $pendingSlab));
    }

    public function test_super_admin_can_manage_rates_too(): void
    {
        $slab = $this->makeSlab((string) $this->company->id);

        $this->assertTrue($this->superAdmin->can('viewAny', TaxSlab::class));
        $this->assertTrue($this->superAdmin->can('create', TaxSlab::class));
        $this->assertTrue($this->superAdmin->can('view', $slab));
        $this->assertTrue($this->superAdmin->can('update', $slab));
        $this->assertTrue($this->superAdmin->can('delete', $slab));
        $this->assertTrue($this->superAdmin->can('submit', $slab));
        $this->assertTrue($this->superAdmin->can('history', $slab));
    }
}
