<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class EmployeeIbanValidationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createManager(): array
    {
        $company = Company::factory()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'FR',
            'city' => 'Paris',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return [$company, $manager];
    }

    public function test_store_rejects_invalid_iban(): void
    {
        [, $manager] = $this->createManager();
        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@a.test',
                'password' => 'password123',
                'role' => 'employee',
                'iban' => 'NOT-A-VALID-IBAN',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['iban']);
    }

    public function test_store_accepts_valid_fr_iban_and_persists_it_encrypted(): void
    {
        [$company, $manager] = $this->createManager();
        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employees', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@a.test',
                'password' => 'password123',
                'role' => 'employee',
                'iban' => 'FR7630006000011234567890189',
            ]);

        $response->assertStatus(201);

        $employee = Employee::query()->where('email', 'john.doe@a.test')->first();
        $this->assertNotNull($employee);
        $this->assertSame('FR7630006000011234567890189', $employee->iban);

        // Raw DB value must not equal the plaintext IBAN (EncryptedCast at rest).
        $raw = Employee::withoutGlobalScopes()
            ->where('id', $employee->id)
            ->toBase()
            ->first();
        $this->assertNotSame('FR7630006000011234567890189', $raw->iban);
    }

    public function test_update_rejects_invalid_iban(): void
    {
        [$company, $manager] = $this->createManager();

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employee->id}", [
                'iban' => 'FR76300060000112345678901', // one char short
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['iban']);
    }

    public function test_update_accepts_valid_ma_iban(): void
    {
        [$company, $manager] = $this->createManager();

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employee->id}", [
                'iban' => 'MA64011519000001205000534921',
            ]);

        $response->assertOk();
        $this->assertSame('MA64011519000001205000534921', $employee->fresh()->iban);
    }
}
