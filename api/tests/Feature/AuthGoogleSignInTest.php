<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Services\TotpService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
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

    public function test_google_callback_enrolled_2fa_requires_challenge(): void
    {
        // #5579 — un employé ayant activé la 2FA ne reçoit PAS de token via le
        // callback Google : challenge TOTP uniquement (POST /auth/2fa/verify).
        $this->mockGoogleUser('2fa-google@example.com');

        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => '2fa-google@example.com',
            'password_hash' => Hash::make('secret1234'),
            'role' => 'ordinary',
            'status' => 'active',
            'two_fa_secret' => (new TotpService())->generateSecret(),
            'two_fa_enabled_at' => now(),
        ]);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertOk()
            ->assertJsonPath('mfa_challenge', true)
            ->assertJsonStructure(['mfa_challenge_token', 'mfa_challenge_expires_in'])
            ->assertJsonMissingPath('token');

        // Aucun token Sanctum émis pour l'employé.
        $this->assertSame(0, $employee->tokens()->count());
    }


    public function test_google_callback_policy_mfa_required_blocks(): void
    {
        // #5579 — politique tenant mfa_required_roles (rôle sensible) + 2FA
        // non activée → le flux Google bloque, comme le login classique.
        $this->mockGoogleUser('mfa-policy-google@example.com');

        $company = Company::factory()->create(['country' => 'DZ']);
        Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'mfa-policy-google@example.com',
            'password_hash' => Hash::make('secret1234'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        CompanySetting::query()->create([
            'key' => 'mfa_required_roles',
            'company_id' => $company->id,
            'value' => 'rh,principal',
            'value_type' => 'string',
        ]);

        $response = $this->withSession(['google_oauth_state' => 'valid-state'])
            ->getJson('/api/v1/auth/google/callback?state=valid-state');

        $response->assertStatus(403)
            ->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');
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
