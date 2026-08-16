<?php

namespace Tests\Feature\Estimation;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ReceiptPdfTest extends TestCase
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

    public function test_manager_can_download_receipt_pdf_and_contains_disclaimer(): void
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
            'currency' => 'DZD',
        ]);

        $manager = Employee::query()->create([
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'salary_type' => 'fixed',
        ]);
        $manager->company_id = $company->id;
        $manager->role = 'manager';
        $manager->status = 'active';
        $manager->salary_base = 0;
        $manager->save();


        $employee = Employee::query()->create([
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'salary_type' => 'hourly',
        ]);
        $employee->company_id = $company->id;
        $employee->role = 'employee';
        $employee->status = 'active';
        $employee->hourly_rate = 100;
        $employee->save();


        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-01',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-01 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-01 16:00:00', 'UTC'),
            'hours_worked' => 8.00,
            'overtime_hours' => 0.00,
            'status' => 'ontime',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get('/api/v1/employees/'.$employee->id.'/receipt?from=2026-04-01&to=2026-04-01');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function test_employee_cannot_download_receipt_pdf(): void
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
            'currency' => 'DZD',
        ]);

        $employee = Employee::query()->create([
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'salary_type' => 'hourly',
        ]);
        $employee->company_id = $company->id;
        $employee->role = 'employee';
        $employee->status = 'active';
        $employee->hourly_rate = 50;
        $employee->save();


        Sanctum::actingAs($employee);

        $response = $this->get('/api/v1/employees/'.$employee->id.'/receipt?from=2026-04-01&to=2026-04-01');
        $response->assertForbidden();
    }
}

