<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class SuspendedEmployeeGuardTest extends TestCase
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

    /**
     * Verifie qu'un employe suspendu ne peut pas se connecter.
     * Priorite MVP 3 & 7.
     */
    public function test_login_rejects_suspended_employee(): void
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
            'email' => 'suspended@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended@company.test',
            'password' => 'password123',
            'device_name' => 'tests',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_NOT_ACTIVE');
    }

    /**
     * Verifie qu'un employe dont le statut passe a suspendu est bloque par le middleware.
     * Priorite MVP 3 & 7.
     */
    public function test_suspended_employee_token_is_blocked_by_middleware(): void
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
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        // Ok quand actif
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        // Suspendre l'employe
        $employee->status = 'suspended';
        $employee->save();

        // Bloque apres suspension
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'EMPLOYEE_SUSPENDED');
    }
}
