<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class OnboardingQrControllerTest extends TestCase
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

    public function test_manager_can_scan_employee_qr_and_create_employee_from_prefill(): void
    {
        Mail::fake();

        $companyA = Company::query()->create([
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

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'commerce',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $companyA->id,
            'first_name' => 'Meriem',
            'last_name' => 'RH',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $externalEmployee = Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'first_name' => 'Amina',
            'last_name' => 'Belaid',
            'email' => 'amina.personal@example.test',
            'personal_email' => 'amina.personal@example.test',
            'personal_phone' => '+213600000111',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $qr = $this
            ->actingAs($externalEmployee, 'sanctum')
            ->getJson('/api/v1/me/qr-profile')
            ->assertOk()
            ->json('data.token');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => $qr,
            ])
            ->assertOk()
            ->assertJsonPath('data.prefill.first_name', 'Amina')
            ->assertJsonPath('data.prefill.personal_email', 'amina.personal@example.test');

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/create-employee', [
                'qr_token' => $qr,
                'email' => 'amina.pro@a.test',
                'matricule' => 'A-QR-001',
                'contract_start' => '2026-05-27',
                'salary_type' => 'fixed',
                'salary_base' => 85000,
                'extra_data' => [
                    'department' => 'Operations',
                    'job_title' => 'Assistante terrain',
                    'work_location' => 'Alger Est',
                ],
                'send_invitation' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'amina.pro@a.test')
            ->assertJsonPath('data.extra_data.department', 'Operations');

        $this->assertDatabaseHas('employees', [
            'company_id' => $companyA->id,
            'email' => 'amina.pro@a.test',
            'matricule' => 'A-QR-001',
            'salary_base' => 85000,
        ]);
    }

    public function test_employee_qr_rejects_tampered_token(): void
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $this
            ->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/company/qr-onboarding/scan-employee', [
                'qr_token' => 'invalid.payload',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qr_token']);
    }
}
