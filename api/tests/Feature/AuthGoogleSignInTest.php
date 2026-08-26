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
        // #5580 : les comptes Google vérifiés portent email_verified=true.
        $abstractUser->shouldReceive('offsetGet')->with('email_verified')->andReturn(true);

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
            ->assertJson(['error' => 'UNKNOWN_ACCOUNT'])
            // Issue #5171 : le message est localisé ×4 (parité i18n), pas un
            // littéral anglais codé en dur.
            ->assertJsonPath('message', __('errors.UNKNOWN_ACCOUNT'));

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

        $employee = Employee::forceCreate([
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

        $employee = Employee::forceCreate([
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

    public function test_google_callback_unverified_email_is_rejected(): void
    {
        // #5580 — fail-closed : un email Google NON vérifié (email_verified
        // absent ou false) ne peut ni matcher un compte existant, ni être
        // auto-provisionné — 401 GOOGLE_EMAIL_NOT_VERIFIED, aucun token.
        config(['app.demo_mode_enabled' => true]);

        $abstractUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getEmail')->andReturn('unverified@example.com');
        $abstractUser->shouldReceive('getName')->andReturn('Unverified User');
        $abstractUser->shouldReceive('offsetGet')->with('given_name')->andReturn('Unverified');
        $abstractUser->shouldReceive('offsetGet')->with('family_name')->andReturn('User');
        $abstractUser->shouldReceive('offsetGet')->with('email_verified')->andReturn(false);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider = \Mockery::mock('Laravel\Socialite\Two\GoogleProvider'));
        $provider->shouldReceive('stateless')->andReturn($provider);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(401)
            ->assertJson(['error' => 'GOOGLE_EMAIL_NOT_VERIFIED'])
            ->assertJsonPath('message', __('errors.GOOGLE_EMAIL_NOT_VERIFIED'));

        // Même en demo mode, aucune création de compte avec un email non vérifié.
        $this->assertDatabaseMissing('employees', ['email' => 'unverified@example.com']);
    }

    public function test_google_redirect_returns_503_when_oauth_not_configured(): void
    {
        // Issue #5170 : en prod, GOOGLE_CLIENT_ID/SECRET/REDIRECT_URL absents
        // de l'env Render → Socialite ne peut pas construire l'URL →
        // 500 INTERNAL_ERROR (page JSON brute, dead end pour l'utilisateur).
        // Depuis le fix : échec rapide 503 GOOGLE_OAUTH_NOT_CONFIGURED.
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $response = $this->getJson('/api/v1/auth/google');

        $response->assertStatus(503)
            ->assertJson(['error' => 'GOOGLE_OAUTH_NOT_CONFIGURED']);
    }

    public function test_google_redirect_passes_when_oauth_configured(): void
    {
        // Credentials présents → la garde de configuration passe et la
        // redirection Socialite est tentée (sans réseau en test, l'échec
        // éventuel ne doit PAS être GOOGLE_OAUTH_NOT_CONFIGURED).
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://example.com/api/v1/auth/google/callback',
        ]);

        $response = $this->getJson('/api/v1/auth/google');

        $this->assertNotEquals(503, $response->getStatusCode());
        $this->assertNotEquals('GOOGLE_OAUTH_NOT_CONFIGURED', $response->json('error'));
    }
}
