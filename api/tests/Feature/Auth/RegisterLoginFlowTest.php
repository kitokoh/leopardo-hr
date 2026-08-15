<?php

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Parcours register → login (issues #2617 / #2636) : plus d'employé orphelin
 * sans company_id — l'inscription exige une invitation valide (RegisterAction)
 * et rattache l'employé au company_id de l'invitation, donc le login fonctionne.
 */
class RegisterLoginFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    private function createInvitation(string $email, ?string $token = 'valid-token-123'): string
    {
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        DB::table('public.user_invitations')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 0,
            'email' => $email,
            'role' => 'ordinary',
            'manager_role' => null,
            'invited_by_type' => 'platform',
            'invited_by_email' => 'admin@leopardo-rh.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function test_register_without_invitation_creates_no_orphan_employee(): void
    {
        // #2636 : sans invitation valide, aucun employé orphelin (company_id
        // null) n'est créé — le parcours est fail-closed (#2617).
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('employees', ['email' => 'john.doe@example.com']);
    }

    public function test_registered_account_with_invitation_can_login_again(): void
    {
        $token = $this->createInvitation('john.doe@example.com');

        $register = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation_token' => $token,
        ]);

        $register->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'email', 'role'], 'token']);

        // L'employé est rattaché au company_id de l'invitation (plus
        // d'orphelin → plus de CompanyNotFoundException au login).
        $this->assertDatabaseHas('employees', [
            'email' => 'john.doe@example.com',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'john.doe@example.com',
            'password' => 'password123',
        ]);

        $login->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
    }

    public function test_login_requires_active_status_for_pending_company_account(): void
    {
        Employee::create([
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'email' => 'suspended.ordinary@example.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'ordinary',
            'status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended.ordinary@example.com',
            'password' => 'password123',
        ])->assertStatus(403);
    }
}
