<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Language;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AuthProfileSettingsTest extends TestCase
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

    public function test_employee_can_update_own_profile(): void
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
            'first_name' => 'Karim',
            'last_name' => 'Aouad',
            'email' => 'karim@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/auth/profile', [
                'first_name' => 'Karim Updated',
                'last_name' => 'Aouad Updated',
                'email' => 'karim.updated@company.test',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Karim Updated'); }
        $response->assertJsonPath('data.email', 'karim.updated@company.test'); }
    }

    public function test_employee_can_change_password_with_current_password(): void
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

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'password456',
                'new_password_confirmation' => 'password456',
            ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('password456', $employee->fresh()->password_hash));
    }

    public function test_employee_cannot_change_password_with_wrong_current_password(): void
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

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'password456',
                'new_password_confirmation' => 'password456',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'INVALID_CURRENT_PASSWORD'); }
        $response->assertJsonPath('message', 'INVALID_CURRENT_PASSWORD'); }
        $this->assertNotNull($response->json('localized_message'));
        $this->assertTrue(Hash::check('password123', $employee->fresh()->password_hash));
    }

    public function test_employee_can_update_preferred_language_and_receive_rtl_metadata(): void
    {
        Language::query()->create([
            'code' => 'fr',
            'name_fr' => 'Français',
            'name_native' => 'Français',
            'is_rtl' => false,
            'is_active' => true,
        ]);

        Language::query()->create([
            'code' => 'ar',
            'name_fr' => 'Arabe',
            'name_native' => 'العربية',
            'is_rtl' => true,
            'is_active' => true,
        ]);

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
            'language' => 'fr',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/auth/language', [
                'language' => 'ar',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.language', 'ar'); }
        $response->assertJsonPath('data.is_rtl', true);
        $this->assertSame('ar', $employee->fresh()->preferred_language);
    }

    public function test_employee_cannot_select_inactive_language(): void
    {
        Language::query()->create([
            'code' => 'fr',
            'name_fr' => 'Français',
            'name_native' => 'Français',
            'is_rtl' => false,
            'is_active' => true,
        ]);

        Language::query()->create([
            'code' => 'tr',
            'name_fr' => 'Turc',
            'name_native' => 'Türkçe',
            'is_rtl' => false,
            'is_active' => false,
        ]);

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
            'language' => 'fr',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/auth/language', [
                'language' => 'tr',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'VALIDATION_ERROR'); }
        $this->assertNull($employee->fresh()->preferred_language);
    }
}
