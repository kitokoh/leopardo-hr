<?php

namespace Tests\Feature\Security;

use App\Models\CabinetFolder;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CabinetCrossTenantIsolationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_employee_cannot_upload_document_to_another_employees_folder(): void
    {
        $company = $this->createCompany('Test Company');
        $employeeA = $this->createEmployee($company, 'employee');
        $employeeB = $this->createEmployee($company, 'employee');

        // Employee B creates a folder
        $folderB = CabinetFolder::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'name' => 'B Private Folder',
        ]);

        // Employee A tries to upload a document into Employee B's folder
        $file = UploadedFile::fake()->create('secret.pdf', 100);

        $response = $this->actingAs($employeeA, 'sanctum')
            ->postJson('/api/v1/cabinet/documents', [
                'name' => 'Stolen Space',
                'file' => $file,
                'folder_id' => $folderB->id,
            ]);

        // This should fail validation because the folder doesn't belong to employeeA
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_employee_cannot_upload_document_to_another_companys_folder(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $employeeA = $this->createEmployee($companyA, 'employee');
        $employeeB = $this->createEmployee($companyB, 'employee');

        // Company B creates a folder
        $folderB = CabinetFolder::query()->forceCreate([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'name' => 'B Private Folder',
        ]);

        // Employee A tries to upload a document into Company B's folder
        $file = UploadedFile::fake()->create('secret.pdf', 100);

        $response = $this->actingAs($employeeA, 'sanctum')
            ->postJson('/api/v1/cabinet/documents', [
                'name' => 'Cross Company Exploit',
                'file' => $file,
                'folder_id' => $folderB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['folder_id']);
    }

    public function test_employee_cannot_create_folder_under_another_employees_folder(): void
    {
        $company = $this->createCompany('Test Company');
        $employeeA = $this->createEmployee($company, 'employee');
        $employeeB = $this->createEmployee($company, 'employee');

        $folderB = CabinetFolder::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'name' => 'B Private Folder',
        ]);

        $response = $this->actingAs($employeeA, 'sanctum')
            ->postJson('/api/v1/cabinet/folders', [
                'name' => 'Sub Folder',
                'parent_id' => $folderB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_id']);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => strtolower(Str::random(8)).'@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function createEmployee(Company $company, string $role): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'email' => strtolower(Str::random(10)).'@test.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);
    }
}
