<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SocialAccountPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeAccount(string $companyId): SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-xyz',
            'status' => 'active',
        ]);
    }

    public function test_marketing_manager_can_view_and_connect(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);

        $this->assertTrue($manager->can('view', $account));
        $this->assertTrue($manager->can('connect', SocialAccount::class));
        $this->assertTrue($manager->can('disconnect', $account));
    }

    public function test_principal_manager_can_view_and_connect(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->assertTrue($principal->can('view', $account));
        $this->assertTrue($principal->can('connect', SocialAccount::class));
    }

    public function test_hr_manager_cannot_manage_social_account(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $hrManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        $this->assertFalse($hrManager->can('view', $account));
        $this->assertFalse($hrManager->can('connect', SocialAccount::class));
    }

    public function test_regular_employee_cannot_manage_social_account(): void
    {
        $company = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->assertFalse($employee->can('view', $account));
        $this->assertFalse($employee->can('connect', SocialAccount::class));
    }

    public function test_marketing_manager_from_other_company_cannot_view_account(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $account = $this->makeAccount($company->id);

        $manager = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);

        $this->assertFalse($manager->can('view', $account));
    }
}
