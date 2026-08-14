<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1917 — PublicHolidayPolicy : le SuperAdmin plateforme gère tous
 * les fériés (nationaux + entreprises) ; le manager `principal` ne gère que
 * les fériés d'ENTREPRISE de sa société (les nationaux restent en lecture
 * seule pour les tenants).
 */
class PublicHolidayPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected SuperAdmin $superAdmin;

    protected Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $this->companyB = $companyB;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-policy-holiday@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $this->principalA = $principalA;
    }

    private function makeHoliday(?string $companyId): PublicHoliday
    {
        /** @var PublicHoliday $holiday */
        $holiday = PublicHoliday::create([
            'company_id' => $companyId,
            'country_code' => 'DZ',
            'name' => 'Férié test',
            'date' => '2026-07-05',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'fixed',
        ]);

        return $holiday;
    }

    public function test_super_admin_can_manage_all_holidays(): void
    {
        $national = $this->makeHoliday(null);
        $companyHoliday = $this->makeHoliday((string) $this->companyA->id);

        $this->assertTrue($this->superAdmin->can('viewAny', PublicHoliday::class));
        $this->assertTrue($this->superAdmin->can('create', PublicHoliday::class));
        $this->assertTrue($this->superAdmin->can('update', $national));
        $this->assertTrue($this->superAdmin->can('delete', $national));
        $this->assertTrue($this->superAdmin->can('update', $companyHoliday));
        $this->assertTrue($this->superAdmin->can('delete', $companyHoliday));
    }

    public function test_principal_can_manage_own_company_holidays_only(): void
    {
        $own = $this->makeHoliday((string) $this->companyA->id);
        $national = $this->makeHoliday(null);
        $otherCompany = $this->makeHoliday((string) $this->companyB->id);

        $this->assertTrue($this->principalA->can('viewAny', PublicHoliday::class));
        $this->assertTrue($this->principalA->can('create', PublicHoliday::class));
        $this->assertTrue($this->principalA->can('update', $own));
        $this->assertTrue($this->principalA->can('delete', $own));

        // Férié national : lecture seule pour les tenants.
        $this->assertFalse($this->principalA->can('update', $national));
        $this->assertFalse($this->principalA->can('delete', $national));

        // Férié d'une autre entreprise : interdit.
        $this->assertFalse($this->principalA->can('update', $otherCompany));
        $this->assertFalse($this->principalA->can('delete', $otherCompany));
    }

    public function test_non_principal_users_cannot_manage_holidays(): void
    {
        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $this->companyA->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $this->assertFalse($rh->can('viewAny', PublicHoliday::class));
        $this->assertFalse($rh->can('create', PublicHoliday::class));
        $this->assertFalse($employee->can('viewAny', PublicHoliday::class));
        $this->assertFalse($employee->can('create', PublicHoliday::class));
    }
}
