<?php

namespace Tests\Unit;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\EmployeeNotActiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Company;
use App\Models\Employee;
use App\Services\AuthService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Config::set('sanctum.expiration', 60);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_login_updates_last_login_and_returns_token_metadata(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $result = app(AuthService::class)->login('manager@a.test', 'password123', 'unit-tests');

        $this->assertSame($employee->id, $result['employee']->id);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertNotEmpty($result['token']);
        $this->assertNotNull($result['token_expires_at']);
        $this->assertNotNull($employee->fresh()->last_login_at);
        $this->assertDatabaseHas('user_lookups', [
            'email' => 'manager@a.test',
            'employee_id' => $employee->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->expectException(InvalidCredentialsException::class);

        app(AuthService::class)->login('employee@a.test', 'wrong-password');
    }

    public function test_login_rejects_suspended_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'suspended',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->expectException(AccountSuspendedException::class);

        app(AuthService::class)->login('employee@a.test', 'password123');
    }

    public function test_login_rejects_archived_employee(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
        ]);

        $this->expectException(EmployeeNotActiveException::class);

        app(AuthService::class)->login('employee@a.test', 'password123');
    }
}
