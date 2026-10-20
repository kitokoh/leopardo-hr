<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Http\Middleware\Trade\TradeRoleResolver;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRADE-001 (#8476, BC-36 TRADE) — matrice de résolution des rôles Trade
 * (deny-by-default).
 *
 *   - principal            → admin + purchase + sales + logistics + reports ;
 *   - dept / superviseur   → purchase + sales + logistics + reports ;
 *   - rh / comptable       → reports uniquement ;
 *   - employé sans grant   → aucun rôle ;
 *   - employé avec grant `trade.<rôle>` → ce rôle uniquement.
 */
class TradeRoleResolverTest extends TestCase
{
    use RefreshTenantDatabase;

    private TradeRoleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TradeRoleResolver;
    }

    public function test_principal_gets_all_roles(): void
    {
        $employee = $this->makeEmployee('manager', 'principal');

        self::assertSame(
            ['admin', 'purchase', 'sales', 'logistics', 'reports'],
            $this->resolver->rolesFor($employee),
        );
    }

    public function test_dept_manager_gets_operational_roles_without_admin(): void
    {
        $employee = $this->makeEmployee('manager', 'dept');

        $roles = $this->resolver->rolesFor($employee);

        self::assertContains('purchase', $roles);
        self::assertContains('sales', $roles);
        self::assertContains('logistics', $roles);
        self::assertContains('reports', $roles);
        self::assertNotContains('admin', $roles);
    }

    public function test_rh_manager_gets_reports_only(): void
    {
        $employee = $this->makeEmployee('manager', 'rh');

        self::assertSame(['reports'], $this->resolver->rolesFor($employee));
    }

    public function test_employee_without_grant_gets_no_role(): void
    {
        $employee = $this->makeEmployee('employee', null);

        self::assertSame([], $this->resolver->rolesFor($employee));
    }

    public function test_employee_with_trade_grant_gets_that_role_only(): void
    {
        $employee = $this->makeEmployee('employee', null);

        $grant = new EmployeeModuleGrant([
            'employee_id' => $employee->id,
            'module_key' => 'trade.logistics',
        ]);
        $grant->company_id = $employee->company_id;
        $grant->granted_by_employee_id = $employee->id;
        $grant->save();

        self::assertSame(['logistics'], $this->resolver->rolesFor($employee));
        self::assertTrue($this->resolver->hasRole($employee, 'logistics'));
        self::assertFalse($this->resolver->hasRole($employee, 'admin'));
    }

    private function makeEmployee(string $role, ?string $managerRole): Employee
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'currency' => 'XOF']);

        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);
    }
}
