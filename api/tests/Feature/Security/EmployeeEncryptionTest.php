<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeEncryptionTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function it_encrypts_sensitive_data_in_database(): void
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
            'timezone' => 'UTC',
        ]);

        $iban = 'DZ1234567890123456789012';
        $bankAccount = '1234567890';
        $nationalId = '9876543210';

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'encrypted@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'contract_type' => 'CDI',
            'contract_start' => now()->toDateString(),
            'salary_type' => 'fixed',
            'salary_base' => 1000,
            'iban' => $iban,
            'bank_account' => $bankAccount,
            'national_id' => $nationalId,
        ]);

        $this->assertSame($iban, $employee->iban);
        $this->assertSame($bankAccount, $employee->bank_account);
        $this->assertSame($nationalId, $employee->national_id);

        $rawEmployee = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotNull($rawEmployee);
        $this->assertNotSame($iban, $rawEmployee->iban);
        $this->assertNotSame($bankAccount, $rawEmployee->bank_account);
        $this->assertNotSame($nationalId, $rawEmployee->national_id);
        $this->assertNotEmpty($rawEmployee->iban);
    }
}
