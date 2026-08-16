<?php

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\AuthService;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\EmployeeNotActiveException;
use App\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee->company_id = $company->id;
        $employee->role = 'manager';
        $employee->status = 'active';
        $employee->save();


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

    public function test_login_resolves_public_company_when_tenant_schema_shadows_companies_table(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL search_path shadowing only.');
        }

        $company = Company::query()->create([
            'name' => 'Company Shadow',
            'slug' => 'company-shadow',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'shadow@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'email' => 'shadow-manager@company.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee->company_id = $company->id;
        $employee->role = 'manager';
        $employee->status = 'active';
        $employee->save();


        DB::statement('CREATE TABLE IF NOT EXISTS shared_tenants.companies (LIKE public.companies INCLUDING ALL)');
        DB::statement('SET search_path TO public');

        $result = app(AuthService::class)->login('shadow-manager@company.test', 'password123', 'unit-tests');

        $this->assertSame($employee->id, $result['employee']->id);
        $this->assertSame($company->id, $result['employee']->company->id);
        $this->assertNotEmpty($result['token']);
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
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee2->company_id = $company->id;
        $employee2->role = 'employee';
        $employee2->status = 'active';
        $employee2->save();


        $this->expectException(InvalidCredentialsException::class);

        app(AuthService::class)->login('employee@a.test', 'wrong-password');
    }

    public function test_login_with_orphaned_schema_returns_invalid_credentials(): void
    {
        // Issue #2902 : un compte dont le user_lookups pointe vers un schéma
        // tenant inexistant (état prod avec seed partiel) doit produire un
        // 401 explicite — JAMAIS un 500 « Server Error ».
        // Le schéma MVP de test (CreatesMvpSchema) n'a pas de timestamps
        // sur user_lookups — insertion sans created_at/updated_at.
        DB::table('user_lookups')->insert([
            'email' => 'ghost@orphan.test',
            'company_id' => '99999999-9999-9999-9999-999999999999',
            'employee_id' => 999999,
            'schema_name' => 'schema_inexistant_xyz',
            'role' => 'employee',
        ]);

        $this->expectException(InvalidCredentialsException::class);

        app(AuthService::class)->login('ghost@orphan.test', 'password123', 'unit-tests');
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
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee3->company_id = $company->id;
        $employee3->role = 'employee';
        $employee3->status = 'active';
        $employee3->save();


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
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
        ]);
        $employee4->company_id = $company->id;
        $employee4->role = 'employee';
        $employee4->status = 'archived';
        $employee4->save();


        $this->expectException(EmployeeNotActiveException::class);

        app(AuthService::class)->login('employee@a.test', 'password123');
    }
}
