<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3065 (QA 2026-08-15) : POST /employees/link-user ne doit pas
 * permettre de lier un utilisateur à un employé d'une AUTRE société
 * (lien cross-tenant interdit, Constitution §II).
 */
class UserEmployeeLinkCrossTenantTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_cannot_link_user_to_employee_of_another_company(): void
    {
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyB = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $manager = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        $user = User::query()->forceCreate([
            // #5034 : users.first_name/last_name sont NOT NULL (public 000002).
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password_hash' => Hash::make('password123'),
        ]);
        $user->forceFill([
            'status' => 'active',
        ])->save();

        Sanctum::actingAs($manager);

        // L'employé appartient à la société B : le lien doit être refusé (404,
        // pas de fuite d'existence cross-tenant).
        $response = $this->postJson('/api/v1/employees/link-user', [
            'email' => 'john.doe@example.com',
            'employee_id' => $employeeB->id,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error', 'EMPLOYEE_NOT_FOUND');

        $this->assertDatabaseMissing('user_employee_links', [
            'employee_id' => $employeeB->id,
            'company_id' => $companyA->id,
        ]);
    }

    public function test_manager_can_link_user_to_own_company_employee(): void
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $user = User::query()->forceCreate([
            // #5034 : users.first_name/last_name sont NOT NULL (public 000002).
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'password_hash' => Hash::make('password123'),
        ]);
        $user->forceFill([
            'status' => 'active',
        ])->save();

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/employees/link-user', [
            'email' => 'jane.doe@example.com',
            'employee_id' => $employee->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    }
}
