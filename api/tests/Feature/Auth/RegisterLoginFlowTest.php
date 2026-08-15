<?php

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2617) : le parcours self-onboarding
 * « register → login → company request » doit fonctionner de bout en bout
 * pour un compte ordinary sans entreprise (avant approbation de la demande).
 */
class RegisterLoginFlowTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_registered_ordinary_account_can_login_again_without_company(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'email', 'role'], 'token']);

        // L'ancien code jetait CompanyNotFoundException → login impossible.
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
