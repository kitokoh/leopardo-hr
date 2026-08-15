<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Audit expert 2026-08-15 (issue #2619) : le callback Google valide le
 * paramètre `state` (anti-CSRF), auto-provisionne les comptes inconnus
 * (rôle ordinary, sans tenant) et bloque les comptes suspendus.
 */
class AuthGoogleSignInTest extends TestCase
{
    use RefreshTenantDatabase;

    private function mockGoogleUser(string $email, string $name = 'Google User'): void
    {
        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn($email);
        $abstractUser->shouldReceive('getName')->andReturn($name);
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Google');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);
    }

    public function test_google_callback_without_valid_state_is_rejected(): void
    {
        $this->mockGoogleUser('google@example.com');

        $response = $this->getJson('/api/v1/auth/google/callback?state=forged-state');

        $response->assertStatus(400)
            ->assertJson(['error' => 'INVALID_OAUTH_STATE']);
    }

    public function test_google_callback_unknown_user_is_rejected_without_demo_mode(): void
    {
        // Issue #3724 : en production (DEMO_MODE_ENABLED=false, défaut), un
        // email Google inconnu ne doit PAS être auto-provisionné — 401 et
        // aucune création en base (parité avec /auth/google/token).
        config(['app.demo_mode_enabled' => false]);
        $this->mockGoogleUser('unknown@example.com');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNKNOWN_ACCOUNT']);

        $this->assertDatabaseMissing('employees', ['email' => 'unknown@example.com']);
    }

    public function test_google_callback_with_valid_state_unknown_user_is_auto_created_in_demo_mode(): void
    {
        // Issue #3724 : l'auto-provisionnement des comptes inconnus reste
        // possible uniquement sur les environnements de démo explicitement
        // configurés (DEMO_MODE_ENABLED=true) — 201 + token.
        config(['app.demo_mode_enabled' => true]);
        $this->mockGoogleUser('unknown@example.com');

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);

        $this->assertDatabaseHas('employees', ['email' => 'unknown@example.com', 'role' => 'ordinary']);
    }

    public function test_google_callback_existing_active_user_gets_token(): void
    {
        $this->mockGoogleUser('google@example.com');

        $employee = Employee::create([
            'first_name' => 'Google',
            'last_name' => 'User',
            'email' => 'google@example.com',
            'password_hash' => Hash::make('secret1234'),
        ]);
        $employee->company_id = null;
        $employee->role = 'ordinary';
        $employee->status = 'active';
        $employee->save();

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
    }

    public function test_google_callback_suspended_user_is_rejected(): void
    {
        $this->mockGoogleUser('suspended@example.com');

        $employee = Employee::create([
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'email' => 'suspended@example.com',
            'password_hash' => Hash::make('secret1234'),
        ]);
        $employee->company_id = null;
        $employee->role = 'ordinary';
        $employee->status = 'suspended';
        $employee->save();

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(403);
    }
}
